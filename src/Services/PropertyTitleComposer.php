<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Integra la gestión del inmueble (venta, arriendo…) dentro del título, en vez
 * de pegarla al final.
 *
 *   "Apartamento en Chapinero, Bogotá"  → "Apartamento en venta en Chapinero, Bogotá"
 *   "Casa, 3 habitaciones en Cali"      → "Casa en arriendo, 3 habitaciones en Cali"
 *   "Apartamento - Chapinero"           → "Apartamento en venta - Chapinero"
 *   "Hermoso apartamento con vista"     → "Hermoso apartamento con vista en venta"
 *   "Venta de casa en Envigado"         → sin cambios (ya menciona la gestión)
 */
final class PropertyTitleComposer
{
    /**
     * Palabras que ya expresan cada gestión en un título escrito a mano. Si el
     * título contiene alguna, no se vuelve a agregar.
     */
    private const OPERATION_SYNONYMS = [
        'venta'    => ['venta', 'ventas', 'vende', 'vendo', 'vender'],
        'arriendo' => ['arriendo', 'arriendos', 'arrienda', 'arrendar', 'arrendamiento', 'alquiler', 'alquila', 'renta'],
    ];

    /**
     * Separa el título en tres partes para poder envolver la gestión en su propio
     * elemento: [antes, frase de gestión, después]. Si no hay nada que insertar,
     * la frase queda vacía y "antes" contiene el título completo.
     *
     * @param list<string> $operations Nombres de los términos de gestión.
     * @return array{0:string,1:string,2:string}
     */
    public static function split(string $title, array $operations): array
    {
        $title = trim($title);
        $operations = array_values(array_filter(array_map(
            static fn($name): string => trim((string) $name),
            $operations
        ), static fn(string $name): bool => $name !== ''));

        if ($title === '' || $operations === [] || self::mentionsOperation($title, $operations)) {
            return [$title, '', ''];
        }

        $phrase = 'en ' . self::joinOperations($operations);
        if (self::isUppercase($title)) {
            $phrase = mb_strtoupper($phrase, 'UTF-8');
        }

        $position = self::insertionPoint($title);
        if ($position === null) {
            return [$title . ' ', $phrase, ''];
        }

        return [substr($title, 0, $position) . ' ', $phrase, substr($title, $position)];
    }

    /**
     * @param list<string> $operations
     */
    public static function compose(string $title, array $operations): string
    {
        return implode('', self::split($title, $operations));
    }

    /**
     * "venta", "venta y arriendo", "venta, arriendo y permuta".
     *
     * @param list<string> $operations
     */
    private static function joinOperations(array $operations): string
    {
        $names = array_values(array_unique(array_map(
            static fn(string $name): string => self::lowerFirst($name),
            $operations
        )));

        if (count($names) === 1) {
            return $names[0];
        }

        $last = array_pop($names);

        return implode(', ', $names) . ' y ' . $last;
    }

    /**
     * Posición (en bytes) del primer conector de ubicación o separador, donde
     * la gestión encaja de forma natural. null si el título no tiene ninguno.
     */
    private static function insertionPoint(string $title): ?int
    {
        if (!preg_match('/\s+en\s+|\s*,\s*|\s+[-–—|·]\s+|\s*:\s+/iu', $title, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $offset = (int) $match[0][1];

        // Un separador al inicio no deja un tipo de inmueble al que unir la gestión.
        return $offset > 0 ? $offset : null;
    }

    /**
     * @param list<string> $operations
     */
    private static function mentionsOperation(string $title, array $operations): bool
    {
        $normalizedTitle = self::normalize($title);

        foreach ($operations as $operation) {
            $key = self::normalize($operation);
            $words = self::OPERATION_SYNONYMS[$key] ?? [$key];

            foreach ($words as $word) {
                if ($word !== '' && preg_match('/(^|[^a-z0-9])' . preg_quote($word, '/') . '($|[^a-z0-9])/', $normalizedTitle)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        return function_exists('remove_accents') ? remove_accents($value) : $value;
    }

    /**
     * "Venta" y "VENTA" → "venta"; "Arriendo Temporal" → "arriendo temporal";
     * se respetan siglas cortas dentro de un nombre mixto ("Venta VIS" → "venta VIS").
     */
    private static function lowerFirst(string $value): string
    {
        $allUpper = mb_strtoupper($value, 'UTF-8') === $value;
        $words = preg_split('/(\s+)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$value];

        foreach ($words as $index => $word) {
            $isAcronym = !$allUpper
                && mb_strlen($word, 'UTF-8') <= 4
                && preg_match('/\p{L}/u', $word)
                && mb_strtoupper($word, 'UTF-8') === $word;

            if (!$isAcronym) {
                $words[$index] = mb_strtolower($word, 'UTF-8');
            }
        }

        return implode('', $words);
    }

    private static function isUppercase(string $title): bool
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $title) ?? '';

        return mb_strlen($letters, 'UTF-8') > 3 && mb_strtoupper($letters, 'UTF-8') === $letters;
    }
}
