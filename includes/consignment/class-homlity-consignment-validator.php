<?php
/**
 * Homlity Consignment Validator.
 *
 * All methods return an associative array of field-path → error-string.
 * An empty array means validation passed.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Homlity_Consignment_Validator
{
    // ── Per-step validation ───────────────────────────────────────────────

    public static function validateStep(string $step, array $data): array
    {
        $opts = Homlity_Consignment_Manager::options();

        return match ($step) {
            'contact'          => self::contact($data, $opts),
            'operation'        => self::operation($data),
            'location'         => self::location($data, $opts),
            'property_details' => self::propertyDetails($data),
            'areas'            => self::areas($data),
            'features'         => [],   // optional step — no required fields
            'media'            => self::media($data, $opts),
            'advisor'          => self::advisor($data),
            'review'           => self::review($data),
            default            => [],
        };
    }

    // ── Full-form validation (called on submit) ───────────────────────────

    public static function validateAll(array $body): array
    {
        $opts   = Homlity_Consignment_Manager::options();
        $errors = [];

        $steps = [
            'contact'          => fn() => self::contact($body['contact'] ?? [], $opts),
            'operation'        => fn() => self::operation($body['operation'] ?? []),
            'location'         => fn() => self::location($body['location'] ?? [], $opts),
            'property_details' => fn() => self::propertyDetails($body['property_details'] ?? []),
            'areas'            => fn() => self::areas($body['areas'] ?? []),
            'media'            => fn() => self::media($body['media'] ?? [], $opts),
            'advisor'          => fn() => self::advisor($body['advisor'] ?? []),
            'review'           => fn() => self::review($body['review'] ?? []),
        ];

        foreach ($steps as $step => $fn) {
            $step_errors = $fn();
            foreach ($step_errors as $field => $msg) {
                $errors["{$step}.{$field}"] = $msg;
            }
        }

        // Cross-step: price vs operation
        $ops         = (array) ($body['operation']['operation'] ?? []);
        $operation   = is_array($ops) ? implode(',', $ops) : (string) ($body['operation']['operation'] ?? '');
        $sale_price  = trim((string) ($body['operation']['sale_price'] ?? ''));
        $rent_price  = trim((string) ($body['operation']['rent_price'] ?? ''));

        if ((stripos($operation, 'Venta') !== false) && ($sale_price === '' || (float) $sale_price <= 0)) {
            $errors['operation.sale_price'] = 'El precio de venta es obligatorio cuando la operación incluye Venta.';
        }
        if ((stripos($operation, 'Arriendo') !== false) && ($rent_price === '' || (float) $rent_price <= 0)) {
            $errors['operation.rent_price'] = 'El precio de arriendo es obligatorio cuando la operación incluye Arriendo.';
        }

        // Provider allowed check
        $allowed_providers = apply_filters('homlity_consignment_allowed_providers', ['public-consignment']);
        $provider = sanitize_key($body['_provider'] ?? $opts['provider'] ?? 'public-consignment');
        if (!in_array($provider, $allowed_providers, true)) {
            $errors['_provider'] = 'Proveedor no permitido.';
        }

        return apply_filters('homlity_consignment_validation_rules', $errors, $body, $opts);
    }

    // ── Step validators ───────────────────────────────────────────────────

    private static function contact(array $d, array $opts): array
    {
        $errors = [];

        $allowed_types = [];
        if ($opts['allow_owners'])   $allowed_types[] = 'owner';
        if ($opts['allow_advisors']) { $allowed_types[] = 'advisor'; $allowed_types[] = 'authorized'; }
        if ($opts['allow_agencies']) $allowed_types[] = 'agency';
        $allowed_types[] = 'builder';

        $type = sanitize_key($d['consignant_type'] ?? '');
        if ($type === '') {
            $errors['consignant_type'] = 'Selecciona el tipo de consignante.';
        } elseif (!in_array($type, $allowed_types, true)) {
            $errors['consignant_type'] = 'Tipo de consignante no permitido.';
        }

        $name = trim($d['name'] ?? '');
        if (strlen($name) < 2) {
            $errors['name'] = 'El nombre debe tener al menos 2 caracteres.';
        }

        $email = trim($d['email'] ?? '');
        if ($email === '') {
            $errors['email'] = 'El correo electrónico es obligatorio.';
        } elseif (!is_email($email)) {
            $errors['email'] = 'Ingresa un correo electrónico válido.';
        }

        $phone    = trim($d['phone'] ?? '');
        $whatsapp = trim($d['whatsapp'] ?? '');
        if ($phone === '' && $whatsapp === '') {
            $errors['phone'] = 'Debes ingresar al menos un número de teléfono o WhatsApp.';
        }

        if (empty($d['data_consent'])) {
            $errors['data_consent'] = 'Debes aceptar la política de tratamiento de datos.';
        }
        if (empty($d['authorization_consent'])) {
            $errors['authorization_consent'] = 'Debes confirmar que tienes autorización para consignar este inmueble.';
        }

        return $errors;
    }

    private static function operation(array $d): array
    {
        $errors    = [];
        $operation = trim($d['operation'] ?? '');

        if ($operation === '') {
            $errors['operation'] = 'Selecciona el tipo de operación.';
            return $errors;
        }

        if (stripos($operation, 'Venta') !== false) {
            $price = trim($d['sale_price'] ?? '');
            if ($price === '' || (float) preg_replace('/[^0-9.]/', '', $price) <= 0) {
                $errors['sale_price'] = 'El precio de venta es obligatorio.';
            }
        }

        if (stripos($operation, 'Arriendo') !== false) {
            $price = trim($d['rent_price'] ?? '');
            if ($price === '' || (float) preg_replace('/[^0-9.]/', '', $price) <= 0) {
                $errors['rent_price'] = 'El precio de arriendo es obligatorio.';
            }
        }

        return $errors;
    }

    private static function location(array $d, array $opts): array
    {
        $errors = [];

        if (trim($d['country'] ?? '') === '') {
            $errors['country'] = 'El país es obligatorio.';
        }
        if (trim($d['state'] ?? '') === '') {
            $errors['state'] = 'El departamento/estado es obligatorio.';
        }
        if (trim($d['city'] ?? '') === '') {
            $errors['city'] = 'La ciudad es obligatoria.';
        }
        if (trim($d['address'] ?? '') === '') {
            $errors['address'] = 'La dirección es obligatoria.';
        }

        if ($opts['require_coordinates']) {
            $lat = trim($d['latitude'] ?? '');
            $lng = trim($d['longitude'] ?? '');
            if ($lat === '' || !is_numeric($lat)) {
                $errors['latitude'] = 'La latitud es obligatoria.';
            }
            if ($lng === '' || !is_numeric($lng)) {
                $errors['longitude'] = 'La longitud es obligatoria.';
            }
        }

        // Block RFC-1918 and localhost URLs in maps_url
        if (!empty($d['maps_url'])) {
            if (!self::isSafeExternalUrl($d['maps_url'])) {
                $errors['maps_url'] = 'URL de Google Maps no válida.';
            }
        }

        return $errors;
    }

    private static function propertyDetails(array $d): array
    {
        $errors = [];

        $title = trim($d['title'] ?? '');
        if ($title === '') {
            $errors['title'] = 'El título del inmueble es obligatorio.';
        } elseif (mb_strlen($title) > 200) {
            $errors['title'] = 'El título no debe superar los 200 caracteres.';
        }

        if (trim($d['property_type'] ?? '') === '') {
            $errors['property_type'] = 'El tipo de inmueble es obligatorio.';
        }

        if (!empty($d['year_built'])) {
            $year = (int) $d['year_built'];
            if ($year < 1800 || $year > (int) gmdate('Y') + 10) {
                $errors['year_built'] = 'Ingresa un año de construcción válido.';
            }
        }

        return $errors;
    }

    private static function areas(array $d): array
    {
        $errors = [];

        $area = trim($d['area'] ?? '');
        if ($area === '' || (float) $area <= 0) {
            $errors['area'] = 'El área del inmueble es obligatoria.';
        }

        foreach (['bedrooms', 'bathrooms', 'parking'] as $field) {
            $val = $d[$field] ?? '';
            if ($val !== '' && (!is_numeric($val) || (int) $val < 0)) {
                $label = ['bedrooms' => 'Habitaciones', 'bathrooms' => 'Baños', 'parking' => 'Parqueaderos'][$field];
                $errors[$field] = "{$label} debe ser un número entero mayor o igual a cero.";
            }
        }

        return $errors;
    }

    private static function media(array $d, array $opts): array
    {
        $errors  = [];
        $gallery = array_filter((array) ($d['gallery'] ?? []));

        if ($opts['require_image'] && empty($gallery) && empty(trim($d['featured_image'] ?? ''))) {
            $errors['gallery'] = 'Debes subir al menos una imagen del inmueble.';
        }

        // Validate gallery URLs don't point to internal/private addresses
        foreach ($gallery as $url) {
            if (!self::isSafeMediaUrl($url)) {
                $errors['gallery'] = 'Una o más imágenes tienen una URL no válida.';
                break;
            }
        }

        if (!empty($d['brochure']) && !self::isSafeMediaUrl($d['brochure'])) {
            $errors['brochure'] = 'La URL del brochure no es válida.';
        }

        return $errors;
    }

    private static function advisor(array $d): array
    {
        $errors = [];

        // Only validate if advisor data is provided
        if (empty($d['name']) && empty($d['email']) && empty($d['phone'])) {
            return [];
        }

        if (!empty($d['email']) && !is_email($d['email'])) {
            $errors['email'] = 'Ingresa un correo válido para el asesor.';
        }

        return $errors;
    }

    private static function review(array $d): array
    {
        $errors = [];

        if (empty($d['truth_declaration'])) {
            $errors['truth_declaration'] = 'Debes confirmar que la información suministrada es verídica.';
        }
        if (empty($d['contact_consent'])) {
            $errors['contact_consent'] = 'Debes aceptar ser contactado para validar la consignación.';
        }

        return $errors;
    }

    // ── Security helpers ──────────────────────────────────────────────────

    private static function isSafeExternalUrl(string $url): bool
    {
        $parsed = wp_parse_url($url);
        if (!isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }
        if (!in_array($parsed['scheme'], ['http', 'https'], true)) {
            return false;
        }
        return !self::isPrivateHost($parsed['host']);
    }

    private static function isSafeMediaUrl(string $url): bool
    {
        if (empty($url)) {
            return true;
        }
        $parsed = wp_parse_url($url);
        if (!isset($parsed['host'])) {
            return false;  // relative URLs or data URIs not allowed
        }
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'], true)) {
            return false;
        }
        return !self::isPrivateHost($parsed['host']);
    }

    private static function isPrivateHost(string $host): bool
    {
        // Block localhost and RFC-1918 ranges
        $private_patterns = [
            '/^localhost$/i',
            '/^127\.\d+\.\d+\.\d+$/',
            '/^10\.\d+\.\d+\.\d+$/',
            '/^172\.(1[6-9]|2\d|3[01])\.\d+\.\d+$/',
            '/^192\.168\.\d+\.\d+$/',
            '/^::1$/',
        ];
        foreach ($private_patterns as $pattern) {
            if (preg_match($pattern, $host)) {
                return true;
            }
        }
        return false;
    }
}
