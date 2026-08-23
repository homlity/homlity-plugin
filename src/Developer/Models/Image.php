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
 * One image of a property gallery.
 *
 * Homlity stores galleries in several shapes depending on where the property
 * came from — a CSV of attachment IDs when edited in wp-admin, an array of
 * absolute URLs when pulled from a CRM, a JSON blob in older installs. This
 * object hides all of that: an Image always has a URL, and it has an
 * attachment ID only when the file actually lives in the media library.
 *
 * @since 2.8.0
 */
final class Image
{
    private string $url;
    private int $attachmentId;
    private string $alt;

    /**
     * @internal Built by {@see \Homlity\Developer\Services\PropertyRepository}.
     *
     * @param string $url          Absolute URL of the full-size image.
     * @param int    $attachmentId Media library ID, or 0 for a remote image.
     * @param string $alt          Alternative text.
     */
    public function __construct(string $url, int $attachmentId = 0, string $alt = '')
    {
        $this->url          = $url;
        $this->attachmentId = max(0, $attachmentId);
        $this->alt          = $alt;
    }

    /** @since 2.8.0 */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Media library attachment ID, or 0 when the image is hosted elsewhere.
     *
     * @since 2.8.0
     */
    public function getAttachmentId(): int
    {
        return $this->attachmentId;
    }

    /** @since 2.8.0 */
    public function getAlt(): string
    {
        return $this->alt;
    }

    /**
     * Whether the file lives in this site's media library.
     *
     * @since 2.8.0
     */
    public function isLocal(): bool
    {
        return $this->attachmentId > 0;
    }

    /**
     * URL for a registered image size. Falls back to the full-size URL for
     * remote images, which have no generated sizes.
     *
     * @since 2.8.0
     *
     * @param string $size Registered WordPress image size.
     */
    public function getSizeUrl(string $size = 'large'): string
    {
        if ($this->attachmentId <= 0) {
            return $this->url;
        }

        $src = wp_get_attachment_image_src($this->attachmentId, $size);

        return is_array($src) && !empty($src[0]) ? (string) $src[0] : $this->url;
    }

    /**
     * @since 2.8.0
     *
     * @return array{url: string, attachment_id: int, alt: string}
     */
    public function toArray(): array
    {
        return [
            'url'           => $this->url,
            'attachment_id' => $this->attachmentId,
            'alt'           => $this->alt,
        ];
    }

    /** @since 2.8.0 */
    public function __toString(): string
    {
        return $this->url;
    }
}
