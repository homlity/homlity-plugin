<?php

namespace Homlity\PluginInmobiliario\Homologation;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Defines canonical entity types for the homologation system.
 *
 * Each entity type maps to a WordPress taxonomy. When an external source
 * (CRM, integration) sends data, it is resolved through HomologationService
 * to a canonical WordPress term using these types.
 */
class EntityType
{
    public const FEATURE      = 'feature';
    public const COUNTRY      = 'country';
    public const STATE        = 'state';
    public const CITY         = 'city';
    public const NEIGHBORHOOD = 'neighborhood';
    public const OPERATION    = 'operation';
    public const TYPE         = 'type';
    public const CATEGORY     = 'category';
    public const CURRENCY     = 'currency';

    /** Maps entity type → WordPress taxonomy slug. */
    public const TAXONOMY_MAP = [
        self::FEATURE      => 'property_feature',
        self::COUNTRY      => 'property_country',
        self::STATE        => 'property_state',
        self::CITY         => 'property_city',
        self::NEIGHBORHOOD => 'property_neighborhood',
        self::OPERATION    => 'property_operation',
        self::TYPE         => 'property_type',
        self::CATEGORY     => 'property_category',
    ];

    /** Human-readable labels for admin UI. */
    public const LABELS = [
        self::FEATURE      => 'Características',
        self::COUNTRY      => 'Países',
        self::STATE        => 'Departamentos / Provincias',
        self::CITY         => 'Ciudades / Municipios',
        self::NEIGHBORHOOD => 'Barrios',
        self::OPERATION    => 'Tipos de gestión',
        self::TYPE         => 'Tipos de inmueble',
        self::CATEGORY     => 'Categorías',
        self::CURRENCY     => 'Monedas',
    ];

    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    public static function taxonomy(string $entityType): ?string
    {
        return self::TAXONOMY_MAP[$entityType] ?? null;
    }

    public static function label(string $entityType): string
    {
        return self::LABELS[$entityType] ?? $entityType;
    }

    public static function isValid(string $entityType): bool
    {
        return array_key_exists($entityType, self::LABELS);
    }
}
