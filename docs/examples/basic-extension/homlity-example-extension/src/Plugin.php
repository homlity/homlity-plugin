<?php

declare(strict_types=1);

/**
 * @package HomlityExample
 */

namespace HomlityExample;

use Homlity\Developer\Contracts\ExtensionInterface;
use Homlity\Developer\Events\PropertyChanges;
use Homlity\Developer\Events\PropertyContext;
use Homlity\Developer\Extension\Requirements;
use Homlity\Developer\Models\Property;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Una extensión de Homlity completa y funcional, en unas cien líneas.
 *
 * Hace tres cosas, que son las tres que hace cualquier integración real:
 *
 * 1. escucha el ciclo de vida de los inmuebles para enterarse de los cambios;
 * 2. decide qué merece un viaje al sistema externo y qué no;
 * 3. modifica lo que Homlity guarda, con un filtro público.
 *
 * En lugar de hablar con un CRM de verdad escribe en una opción de WordPress
 * que puedes inspeccionar desde la consola:
 *
 *     wp option get homlity_example_synced_log --format=json
 */
final class Plugin implements ExtensionInterface
{
    /** Opción donde se deja constancia de lo que se habría enviado fuera. */
    private const LOG_OPTION = 'homlity_example_synced_log';

    /** Cuántas entradas se conservan. Un log sin tope acaba siendo un problema. */
    private const LOG_LIMIT = 50;

    public function getName(): string
    {
        return __('Homlity Example Extension', 'homlity-example-extension');
    }

    public function getSlug(): string
    {
        // Prefijado con el vendor: dos extensiones no pueden compartir slug.
        return 'homlity-example';
    }

    public function getVersion(): string
    {
        return HOMLITY_EXAMPLE_VERSION;
    }

    public function getRequirements(): Requirements
    {
        return Requirements::create([
            'homlity' => HOMLITY_EXAMPLE_REQUIRES_HOMLITY,
            'api'     => '1.0.0',
            'php'     => '8.0',
        ]);
    }

    /**
     * Homlity llama aquí una sola vez, cuando ya comprobó los requisitos.
     *
     * Es el sitio para enganchar hooks, no para trabajar: en este momento
     * todavía no están registrados los custom post types.
     */
    public function boot(): void
    {
        add_action('homlity/property/created', [$this, 'onPropertyCreated'], 10, 2);
        add_action('homlity/property/updated', [$this, 'onPropertyUpdated'], 10, 3);
        add_action('homlity/property/deleted', [$this, 'onPropertyDeleted'], 10, 2);

        add_filter('homlity/property/normalized', [$this, 'stampImportedProperties'], 10, 2);
    }

    // ─── Acciones ────────────────────────────────────────────────────────

    /**
     * @param Property        $property Inmueble recién creado.
     * @param PropertyContext $context  De dónde vino la escritura.
     */
    public function onPropertyCreated(Property $property, PropertyContext $context): void
    {
        $this->log('created', $property, [
            'origin' => $context->getOrigin(),
            'source' => $context->getSource(),
        ]);
    }

    /**
     * @param Property        $property Inmueble actualizado.
     * @param PropertyChanges $changes  Campos canónicos que cambiaron.
     * @param PropertyContext $context  De dónde vino la escritura.
     */
    public function onPropertyUpdated(Property $property, PropertyChanges $changes, PropertyContext $context): void
    {
        // Un CRM que reenvía un registro idéntico dispara una actualización
        // igual. Sin esta comprobación, cada pase de sincronización sería una
        // llamada al sistema externo que no cambia nada.
        if ($changes->isEmpty()) {
            return;
        }

        // Y si el cambio lo provocó el propio CRM, devolvérselo es un bucle.
        if ($context->getSource() === 'mi-crm') {
            return;
        }

        // Sólo interesa lo comercial: que cambie la descripción no justifica
        // volver a publicar en un portal externo.
        if (!$changes->hasGroup('pricing') && !$changes->has('media.gallery')) {
            return;
        }

        $this->log('updated', $property, ['changed' => $changes->fields()]);
    }

    /**
     * @param Property $property Inmueble a punto de borrarse; todavía se puede leer.
     * @param int      $postId   Su ID.
     */
    public function onPropertyDeleted(Property $property, int $postId): void
    {
        // Aquí es donde una integración real retiraría el inmueble del portal.
        $this->log('deleted', $property, ['post_id' => $postId]);
    }

    // ─── Filtros ─────────────────────────────────────────────────────────

    /**
     * Marca cada inmueble que llega de un CRM con la fecha de importación.
     *
     * @param array<string,mixed> $normalized Carga canónica.
     * @param string              $source     Clave del CRM de origen.
     * @return array<string,mixed>
     */
    public function stampImportedProperties(array $normalized, string $source): array
    {
        if ($source === '') {
            return $normalized;
        }

        $normalized['external']['raw']['imported_by'] = $this->getSlug();
        $normalized['external']['raw']['imported_at'] = gmdate('c');

        return $normalized;
    }

    // ─── Interno ─────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $extra
     */
    private function log(string $event, Property $property, array $extra = []): void
    {
        // Una extensión que lanza dentro de un hook rompe la petición que la
        // invocó — que puede ser el guardado de un inmueble. Nunca dejes que
        // una excepción tuya salga de un callback.
        try {
            $entries = get_option(self::LOG_OPTION, []);
            if (!is_array($entries)) {
                $entries = [];
            }

            $price = $property->getPrice();

            $entries[] = array_merge([
                'event'    => $event,
                'at'       => gmdate('c'),
                'id'       => $property->getId(),
                'code'     => $property->getCode(),
                'title'    => $property->getTitle(),
                'price'    => $price ? $price->getAmount() : null,
                'currency' => $price ? $price->getCurrency() : '',
                'images'   => count($property->getImages()),
                'city'     => $property->getLocation()->getCity(),
            ], $extra);

            update_option(
                self::LOG_OPTION,
                array_slice($entries, -self::LOG_LIMIT),
                false
            );
        } catch (Throwable $error) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[homlity-example] ' . $error->getMessage());
            }
        }
    }
}
