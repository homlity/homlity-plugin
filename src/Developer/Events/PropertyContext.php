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
 * Why and from where a property write happened.
 *
 * Every property lifecycle action receives one. It answers the question an
 * integration always ends up asking: "did *I* cause this?" — so that pushing a
 * change back to the CRM that just sent it is easy to avoid.
 *
 * @since 2.8.0
 */
final class PropertyContext
{
    /** A person edited the property in wp-admin. @since 2.8.0 */
    public const ORIGIN_ADMIN = 'admin';

    /** The write came from a CRM webhook, pull or manual sync. @since 2.8.0 */
    public const ORIGIN_CRM = 'crm';

    /** The write came from the public consignment form. @since 2.8.0 */
    public const ORIGIN_CONSIGNMENT = 'consignment';

    /** The write came from an on-demand lookup by property code. @since 2.8.0 */
    public const ORIGIN_SYNC = 'sync';

    /** The origin could not be determined. @since 2.8.0 */
    public const ORIGIN_UNKNOWN = 'unknown';

    private string $origin;
    private string $source;
    private bool $isNew;

    /**
     * @since 2.8.0
     *
     * @param string $origin One of the ORIGIN_* constants.
     * @param string $source CRM key when the write came from one, else ''.
     * @param bool   $isNew  Whether the property was created by this write.
     */
    public function __construct(string $origin, string $source = '', bool $isNew = false)
    {
        $this->origin = $origin !== '' ? $origin : self::ORIGIN_UNKNOWN;
        $this->source = $source;
        $this->isNew  = $isNew;
    }

    /**
     * One of the ORIGIN_* constants. Unknown values are possible in future
     * versions — compare against the constants, do not assume the full set.
     *
     * @since 2.8.0
     */
    public function getOrigin(): string
    {
        return $this->origin;
    }

    /**
     * The CRM key behind the write (`wasi`, `simi`, …), or an empty string.
     *
     * @since 2.8.0
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Whether the property post was created by this write rather than updated.
     *
     * @since 2.8.0
     */
    public function isNew(): bool
    {
        return $this->isNew;
    }

    /**
     * Whether the write came from outside WordPress — a CRM, the consignment
     * form, or an on-demand sync — rather than from the admin screen.
     *
     * @since 2.8.0
     */
    public function isExternal(): bool
    {
        return in_array(
            $this->origin,
            [self::ORIGIN_CRM, self::ORIGIN_CONSIGNMENT, self::ORIGIN_SYNC],
            true
        );
    }

    /**
     * @since 2.8.0
     *
     * @return array{origin: string, source: string, is_new: bool}
     */
    public function toArray(): array
    {
        return [
            'origin' => $this->origin,
            'source' => $this->source,
            'is_new' => $this->isNew,
        ];
    }
}
