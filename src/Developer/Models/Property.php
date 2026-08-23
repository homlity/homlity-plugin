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
 * The stable, read-only representation of a Homlity property.
 *
 * This is the object every public hook hands to an extension. It exists so
 * that extensions never have to touch `WP_Post`, the `_property_*` meta keys,
 * or the taxonomy term structure — all of which are internal and may change in
 * a minor release.
 *
 * What it deliberately does **not** expose:
 *
 * - the raw CRM payload stored in `_property_sync_payload`;
 * - the owner's identification and contact details captured by the
 *   consignment form (`_property_contact_*`, `_property_identification`);
 * - the consignment consent flags.
 *
 * Those are personal data of a third party and are not part of any public
 * contract. An extension that legitimately needs them must read them itself,
 * with the site owner's consent, and take responsibility for doing so.
 *
 * Build one with `Homlity::properties()->find($postId)`. The constructor is
 * public so tests and integrations can build fixtures, but the array shape it
 * takes is internal — use the repository in production code.
 *
 * @since 2.8.0
 */
final class Property
{
    /** @var array<string,mixed> */
    private array $data;

    private ?Location $location = null;
    private ?Agent $agent = null;

    /** @var Image[]|null */
    private ?array $images = null;

    /**
     * @internal The array shape is internal; use the repository.
     *
     * @param array<string,mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    // ─── Identity ────────────────────────────────────────────────────────

    /**
     * WordPress post ID.
     *
     * @since 2.8.0
     */
    public function getId(): int
    {
        return (int) ($this->data['id'] ?? 0);
    }

    /**
     * Agency-facing property code, e.g. `VTAP1320041`. May be empty.
     *
     * @since 2.8.0
     */
    public function getCode(): string
    {
        return (string) ($this->data['code'] ?? '');
    }

    /** @since 2.8.0 */
    public function getTitle(): string
    {
        return (string) ($this->data['title'] ?? '');
    }

    /**
     * Full description — the post content, unfiltered by `the_content`.
     *
     * @since 2.8.0
     */
    public function getDescription(): string
    {
        return (string) ($this->data['description'] ?? '');
    }

    /** @since 2.8.0 */
    public function getShortDescription(): string
    {
        return (string) ($this->data['short_description'] ?? '');
    }

    /**
     * Canonical public URL of the property.
     *
     * @since 2.8.0
     */
    public function getUrl(): string
    {
        return (string) ($this->data['url'] ?? '');
    }

    // ─── Status ──────────────────────────────────────────────────────────

    /**
     * WordPress post status: `publish`, `draft`, `pending`, `private`, `trash`.
     *
     * @since 2.8.0
     */
    public function getStatus(): string
    {
        return (string) ($this->data['status'] ?? '');
    }

    /**
     * Whether the property is published *and* commercially available.
     *
     * A property can be published but withdrawn from the market; the plugin
     * then serves the "unavailable" landing page instead of the listing.
     *
     * @since 2.8.0
     */
    public function isAvailable(): bool
    {
        return (bool) ($this->data['available'] ?? false);
    }

    /** @since 2.8.0 */
    public function isFeatured(): bool
    {
        return (bool) ($this->data['featured'] ?? false);
    }

    // ─── Classification ──────────────────────────────────────────────────

    /**
     * Primary operation slug — `venta`, `arriendo`, … — or an empty string.
     *
     * @since 2.8.0
     */
    public function getOperation(): string
    {
        return (string) ($this->getOperations()[0] ?? '');
    }

    /**
     * Every operation slug assigned to the property.
     *
     * @since 2.8.0
     *
     * @return string[]
     */
    public function getOperations(): array
    {
        return array_values((array) ($this->data['operations'] ?? []));
    }

    /**
     * Primary property-type slug — `apartamento`, `casa`, … — or an empty string.
     *
     * @since 2.8.0
     */
    public function getPropertyType(): string
    {
        return (string) ($this->getPropertyTypes()[0] ?? '');
    }

    /**
     * @since 2.8.0
     *
     * @return string[]
     */
    public function getPropertyTypes(): array
    {
        return array_values((array) ($this->data['property_types'] ?? []));
    }

    /**
     * Feature slugs (`piscina`, `ascensor`, …), already homologated across CRMs.
     *
     * @since 2.8.0
     *
     * @return string[]
     */
    public function getFeatures(): array
    {
        return array_values((array) ($this->data['features'] ?? []));
    }

    // ─── Pricing ─────────────────────────────────────────────────────────

    /**
     * Sale price, or null when the property is not for sale.
     *
     * @since 2.8.0
     */
    public function getSalePrice(): ?Money
    {
        return ($this->data['sale_price'] ?? null) instanceof Money ? $this->data['sale_price'] : null;
    }

    /**
     * Monthly rent, or null when the property is not for rent.
     *
     * @since 2.8.0
     */
    public function getRentPrice(): ?Money
    {
        return ($this->data['rent_price'] ?? null) instanceof Money ? $this->data['rent_price'] : null;
    }

    /**
     * Monthly administration fee, or null when there is none.
     *
     * @since 2.8.0
     */
    public function getAdminFee(): ?Money
    {
        return ($this->data['admin_fee'] ?? null) instanceof Money ? $this->data['admin_fee'] : null;
    }

    /**
     * The price a visitor sees first: the sale price when there is one,
     * otherwise the rent. Null when the property carries no price at all.
     *
     * @since 2.8.0
     */
    public function getPrice(): ?Money
    {
        return $this->getSalePrice() ?? $this->getRentPrice();
    }

    /**
     * ISO-4217 code of {@see self::getPrice()}, or an empty string.
     *
     * @since 2.8.0
     */
    public function getCurrency(): string
    {
        $price = $this->getPrice();

        return $price instanceof Money ? $price->getCurrency() : '';
    }

    // ─── Metrics ─────────────────────────────────────────────────────────

    /** @since 2.8.0 */
    public function getBedrooms(): int
    {
        return (int) ($this->data['bedrooms'] ?? 0);
    }

    /** @since 2.8.0 */
    public function getBathrooms(): int
    {
        return (int) ($this->data['bathrooms'] ?? 0);
    }

    /** @since 2.8.0 */
    public function getParkingSpaces(): int
    {
        return (int) ($this->data['parking'] ?? 0);
    }

    /**
     * Total area in square metres, or 0.0 when unknown.
     *
     * @since 2.8.0
     */
    public function getArea(): float
    {
        return (float) ($this->data['area'] ?? 0);
    }

    /**
     * Private (built) area in square metres, or 0.0 when unknown.
     *
     * @since 2.8.0
     */
    public function getPrivateArea(): float
    {
        return (float) ($this->data['area_private'] ?? 0);
    }

    /**
     * Socio-economic stratum, a Colombian classification. 0 when not applicable.
     *
     * @since 2.8.0
     */
    public function getStratum(): int
    {
        return (int) ($this->data['stratum'] ?? 0);
    }

    // ─── Relations ───────────────────────────────────────────────────────

    /**
     * @since 2.8.0
     */
    public function getLocation(): Location
    {
        if ($this->location === null) {
            $this->location = new Location((array) ($this->data['location'] ?? []));
        }

        return $this->location;
    }

    /**
     * Gallery images, in display order. Empty when the property has none.
     *
     * @since 2.8.0
     *
     * @return Image[]
     */
    public function getImages(): array
    {
        if ($this->images === null) {
            $this->images = array_values(array_filter(
                (array) ($this->data['images'] ?? []),
                static fn($image): bool => $image instanceof Image
            ));
        }

        return $this->images;
    }

    /**
     * Video URLs declared for the property.
     *
     * @since 2.8.0
     *
     * @return string[]
     */
    public function getVideos(): array
    {
        return array_values((array) ($this->data['videos'] ?? []));
    }

    /**
     * The advisor in charge, or null when nobody is assigned.
     *
     * @since 2.8.0
     */
    public function getAgent(): ?Agent
    {
        if ($this->agent === null) {
            $agentData = (array) ($this->data['agent'] ?? []);
            $hasAgent  = ($agentData['name'] ?? '') !== ''
                || ($agentData['email'] ?? '') !== ''
                || (int) ($agentData['user_id'] ?? 0) > 0;

            if (!$hasAgent) {
                return null;
            }

            $this->agent = new Agent($agentData);
        }

        return $this->agent;
    }

    // ─── Provenance ──────────────────────────────────────────────────────

    /**
     * Key of the CRM this property came from, e.g. `wasi`. Empty when the
     * property was created by hand in wp-admin.
     *
     * @since 2.8.0
     */
    public function getExternalSource(): string
    {
        return (string) ($this->data['external_source'] ?? '');
    }

    /**
     * Identifier of the property in its source CRM. Empty when local.
     *
     * @since 2.8.0
     */
    public function getExternalId(): string
    {
        return (string) ($this->data['external_id'] ?? '');
    }

    /**
     * Whether this property is kept in sync with an external system.
     *
     * @since 2.8.0
     */
    public function isSynced(): bool
    {
        return $this->getExternalSource() !== '' && $this->getExternalId() !== '';
    }

    /**
     * ISO-8601 timestamp of the last successful sync, or an empty string.
     *
     * @since 2.8.0
     */
    public function getLastSyncedAt(): string
    {
        return (string) ($this->data['last_synced_at'] ?? '');
    }

    // ─── Escape hatches ──────────────────────────────────────────────────

    /**
     * The underlying `WP_Post`, or null when it no longer exists.
     *
     * Provided for the cases the model does not cover — rendering a template,
     * reading a custom meta an extension wrote itself. Anything you reach
     * through here is outside the Developer API contract.
     *
     * @since 2.8.0
     */
    public function getPost(): ?\WP_Post
    {
        $post = get_post($this->getId());

        return $post instanceof \WP_Post ? $post : null;
    }

    /**
     * The whole model as a plain, JSON-encodable array.
     *
     * Value objects are flattened through their own `toArray()`. The keys of
     * this array are part of the public contract.
     *
     * @since 2.8.0
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $salePrice = $this->getSalePrice();
        $rentPrice = $this->getRentPrice();
        $adminFee  = $this->getAdminFee();
        $agent     = $this->getAgent();

        return [
            'id'                => $this->getId(),
            'code'              => $this->getCode(),
            'title'             => $this->getTitle(),
            'description'       => $this->getDescription(),
            'short_description' => $this->getShortDescription(),
            'url'               => $this->getUrl(),
            'status'            => $this->getStatus(),
            'available'         => $this->isAvailable(),
            'featured'          => $this->isFeatured(),
            'operations'        => $this->getOperations(),
            'property_types'    => $this->getPropertyTypes(),
            'features'          => $this->getFeatures(),
            'sale_price'        => $salePrice ? $salePrice->toArray() : null,
            'rent_price'        => $rentPrice ? $rentPrice->toArray() : null,
            'admin_fee'         => $adminFee ? $adminFee->toArray() : null,
            'bedrooms'          => $this->getBedrooms(),
            'bathrooms'         => $this->getBathrooms(),
            'parking'           => $this->getParkingSpaces(),
            'area'              => $this->getArea(),
            'area_private'      => $this->getPrivateArea(),
            'stratum'           => $this->getStratum(),
            'location'          => $this->getLocation()->toArray(),
            'images'            => array_map(static fn(Image $i): array => $i->toArray(), $this->getImages()),
            'videos'            => $this->getVideos(),
            'agent'             => $agent ? $agent->toArray() : null,
            'external_source'   => $this->getExternalSource(),
            'external_id'       => $this->getExternalId(),
            'last_synced_at'    => $this->getLastSyncedAt(),
        ];
    }
}
