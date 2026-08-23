<?php

declare(strict_types=1);

/**
 * @package Homlity\Developer
 * @since   2.8.0
 */

namespace Homlity\Developer\Models;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Where a property is.
 *
 * Holds the postal address, the geo hierarchy (country → state → city →
 * neighborhood) as human-readable term names, and the coordinates.
 *
 * `getAddress()` honours the property's "show exact address" flag: when the
 * owner asked to hide it, the method returns an empty string and only the
 * neighborhood-level data is exposed. Extensions that publish listings to
 * third-party portals must not work around this.
 *
 * @since 2.8.0
 */
final class Location
{
    private string $address;
    private string $addressComplement;
    private string $reference;
    private ?float $latitude;
    private ?float $longitude;
    private string $country;
    private string $state;
    private string $city;
    private string $neighborhood;
    private bool $exactAddressPublic;

    /**
     * @internal Built by {@see \Homlity\Developer\Services\PropertyRepository}.
     *
     * @param array<string,mixed> $data
     */
    public function __construct(array $data)
    {
        $this->exactAddressPublic = (bool) ($data['show_exact_address'] ?? true);
        $this->address            = (string) ($data['address'] ?? '');
        $this->addressComplement  = (string) ($data['address_complement'] ?? '');
        $this->reference          = (string) ($data['reference'] ?? '');
        $this->country            = (string) ($data['country'] ?? '');
        $this->state              = (string) ($data['state'] ?? '');
        $this->city               = (string) ($data['city'] ?? '');
        $this->neighborhood       = (string) ($data['neighborhood'] ?? '');

        $latitude  = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;

        $this->latitude  = ($latitude === null || $latitude === '') ? null : (float) $latitude;
        $this->longitude = ($longitude === null || $longitude === '') ? null : (float) $longitude;
    }

    /**
     * Street address, or an empty string when the property hides it.
     *
     * @since 2.8.0
     */
    public function getAddress(): string
    {
        return $this->exactAddressPublic ? $this->address : '';
    }

    /**
     * Apartment / tower / office detail, or an empty string when hidden.
     *
     * @since 2.8.0
     */
    public function getAddressComplement(): string
    {
        return $this->exactAddressPublic ? $this->addressComplement : '';
    }

    /**
     * Free-text landmark reference ("frente al parque"), always public.
     *
     * @since 2.8.0
     */
    public function getReference(): string
    {
        return $this->reference;
    }

    /** @since 2.8.0 */
    public function isExactAddressPublic(): bool
    {
        return $this->exactAddressPublic;
    }

    /** @since 2.8.0 */
    public function getCountry(): string
    {
        return $this->country;
    }

    /** @since 2.8.0 */
    public function getState(): string
    {
        return $this->state;
    }

    /** @since 2.8.0 */
    public function getCity(): string
    {
        return $this->city;
    }

    /** @since 2.8.0 */
    public function getNeighborhood(): string
    {
        return $this->neighborhood;
    }

    /** @since 2.8.0 */
    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    /** @since 2.8.0 */
    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    /** @since 2.8.0 */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * @since 2.8.0
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'address'            => $this->getAddress(),
            'address_complement' => $this->getAddressComplement(),
            'reference'          => $this->reference,
            'country'            => $this->country,
            'state'              => $this->state,
            'city'               => $this->city,
            'neighborhood'       => $this->neighborhood,
            'latitude'           => $this->latitude,
            'longitude'          => $this->longitude,
            'exact_address_public' => $this->exactAddressPublic,
        ];
    }
}
