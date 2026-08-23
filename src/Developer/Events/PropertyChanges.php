<?php

declare(strict_types=1);

/**
 * @package Homlity\Developer
 * @since   2.8.0
 */

namespace Homlity\Developer\Events;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The set of canonical fields a property write actually modified.
 *
 * Fields are named with the canonical dot-paths Homlity already uses as its
 * CRM contract — `pricing.sale_price`, `metrics.bedrooms`, `location.address`,
 * `post.title`, … — not with the internal `_property_*` meta keys. See
 * docs/developers/models/property.md for the full list.
 *
 * A change set may legitimately be empty: a CRM that re-sends an unchanged
 * record still triggers an update, and reporting "nothing changed" is the
 * whole point of handing you this object.
 *
 * @since 2.8.0
 */
final class PropertyChanges
{
    /** @var array<string,array{previous: mixed, current: mixed}> */
    private array $changes;

    /**
     * @internal Built by the plugin core.
     *
     * @param array<string,array{previous: mixed, current: mixed}> $changes
     */
    public function __construct(array $changes = [])
    {
        $this->changes = $changes;
    }

    /**
     * Build a change set by diffing two flat field arrays.
     *
     * Only keys present in $after are compared, so a field the writer did not
     * touch is never reported as removed.
     *
     * @since 2.8.0
     *
     * @param array<string,mixed> $before Values before the write.
     * @param array<string,mixed> $after  Values after the write.
     */
    public static function diff(array $before, array $after): self
    {
        $changes = [];

        foreach ($after as $field => $currentValue) {
            $previousValue = $before[$field] ?? null;

            // Loose-ish comparison on scalars: meta round-trips through strings,
            // so 3 and "3" are the same value and must not read as a change.
            if (is_scalar($previousValue) && is_scalar($currentValue)) {
                if ((string) $previousValue === (string) $currentValue) {
                    continue;
                }
            } elseif ($previousValue === $currentValue) {
                continue;
            }

            $changes[(string) $field] = [
                'previous' => $previousValue,
                'current'  => $currentValue,
            ];
        }

        return new self($changes);
    }

    /**
     * Whether anything changed at all.
     *
     * @since 2.8.0
     */
    public function isEmpty(): bool
    {
        return $this->changes === [];
    }

    /**
     * Whether a specific field changed.
     *
     * @since 2.8.0
     *
     * @param string $field Canonical dot-path, e.g. 'pricing.sale_price'.
     */
    public function has(string $field): bool
    {
        return isset($this->changes[$field]);
    }

    /**
     * Whether any field under a canonical group changed.
     *
     * @since 2.8.0
     *
     * @param string $group Group name, e.g. 'pricing', 'location', 'media'.
     */
    public function hasGroup(string $group): bool
    {
        $prefix = $group . '.';
        foreach (array_keys($this->changes) as $field) {
            if (strncmp((string) $field, $prefix, strlen($prefix)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Names of every field that changed.
     *
     * @since 2.8.0
     *
     * @return string[]
     */
    public function fields(): array
    {
        return array_keys($this->changes);
    }

    /**
     * Value a field had before the write, or null.
     *
     * @since 2.8.0
     *
     * @return mixed
     */
    public function previous(string $field)
    {
        return $this->changes[$field]['previous'] ?? null;
    }

    /**
     * Value a field has after the write, or null.
     *
     * @since 2.8.0
     *
     * @return mixed
     */
    public function current(string $field)
    {
        return $this->changes[$field]['current'] ?? null;
    }

    /**
     * The whole change set, keyed by field.
     *
     * @since 2.8.0
     *
     * @return array<string,array{previous: mixed, current: mixed}>
     */
    public function toArray(): array
    {
        return $this->changes;
    }
}
