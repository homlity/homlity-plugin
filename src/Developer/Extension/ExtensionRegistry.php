<?php

declare(strict_types=1);

/**
 * @package Homlity\Developer
 * @since   2.8.0
 */

namespace Homlity\Developer\Extension;

use Homlity\Developer\Contracts\ExtensionInterface;
use Homlity\Developer\Support\Hooks;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The list of Homlity extensions active on this site.
 *
 * Obtain the registry with `Homlity::extensions()`; do not construct it.
 * Extensions register themselves during the
 * {@see Hooks::EXTENSIONS_REGISTER} action:
 *
 *     add_action('homlity/extensions/register', function ($registry) {
 *         $registry->register(new MyIntegration());
 *     });
 *
 * or, equivalently, with the `homlity_register_extension()` helper.
 *
 * The registry is deliberately forgiving about *when* you register:
 * registering before the action fires queues the extension, registering after
 * it fires boots the extension immediately. It is deliberately strict about
 * *what* you register: a bad slug, a duplicate slug or an unmet requirement is
 * refused and reported instead of half-booting the extension.
 *
 * @since 2.8.0
 */
final class ExtensionRegistry
{
    /** @var array<string,ExtensionInterface> Booted extensions, keyed by slug. */
    private array $booted = [];

    /** @var array<string,ExtensionInterface> Registered but not yet booted, keyed by slug. */
    private array $pending = [];

    /** @var array<string,string[]> Rejection reasons, keyed by slug (or by name when the slug is invalid). */
    private array $failures = [];

    /** Whether {@see self::bootAll()} has already run for this request. */
    private bool $dispatched = false;

    /**
     * Register an extension.
     *
     * Returns false — never throws — when the extension is refused. Inspect
     * {@see self::failures()} for the reasons, and listen to
     * {@see Hooks::EXTENSION_FAILED} to react to them.
     *
     * @since 2.8.0
     *
     * @param ExtensionInterface $extension Extension to register.
     * @return bool True when the extension was accepted.
     */
    public function register(ExtensionInterface $extension): bool
    {
        $slug = $this->readSlug($extension);

        if ($slug === '') {
            $this->fail(
                $this->readName($extension),
                $extension,
                [__('El slug de la extensión está vacío o contiene caracteres no permitidos.', 'homlity-real-estate')]
            );

            return false;
        }

        if (isset($this->booted[$slug]) || isset($this->pending[$slug])) {
            $this->fail($slug, $extension, [
                sprintf(
                    /* translators: %s: extension slug. */
                    __('Ya hay una extensión registrada con el slug «%s».', 'homlity-real-estate'),
                    $slug
                ),
            ]);

            return false;
        }

        $unmet = $this->unmetRequirements($extension);
        if ($unmet !== []) {
            $this->fail($slug, $extension, $unmet);

            return false;
        }

        unset($this->failures[$slug]);

        if ($this->dispatched) {
            return $this->boot($slug, $extension);
        }

        $this->pending[$slug] = $extension;

        return true;
    }

    /**
     * Whether an extension with this slug is registered and booted.
     *
     * @since 2.8.0
     */
    public function has(string $slug): bool
    {
        return isset($this->booted[sanitize_key($slug)]);
    }

    /**
     * A booted extension by slug, or null.
     *
     * @since 2.8.0
     */
    public function get(string $slug): ?ExtensionInterface
    {
        return $this->booted[sanitize_key($slug)] ?? null;
    }

    /**
     * Every booted extension, keyed by slug.
     *
     * @since 2.8.0
     *
     * @return array<string,ExtensionInterface>
     */
    public function all(): array
    {
        return $this->booted;
    }

    /**
     * Reasons why extensions were refused, keyed by slug.
     *
     * @since 2.8.0
     *
     * @return array<string,string[]>
     */
    public function failures(): array
    {
        return $this->failures;
    }

    /**
     * Boot every pending extension and announce the result.
     *
     * @internal Called by the plugin bootstrap. Extensions must not call this.
     */
    public function bootAll(): void
    {
        if ($this->dispatched) {
            return;
        }

        $this->dispatched = true;

        foreach ($this->pending as $slug => $extension) {
            unset($this->pending[$slug]);
            $this->boot($slug, $extension);
        }
    }

    /**
     * Whether extensions have already been booted for this request.
     *
     * @since 2.8.0
     */
    public function isDispatched(): bool
    {
        return $this->dispatched;
    }

    /**
     * Empty the registry.
     *
     * @internal Exists so the test suite can start from a clean state.
     */
    public function reset(): void
    {
        $this->booted     = [];
        $this->pending    = [];
        $this->failures   = [];
        $this->dispatched = false;
    }

    // ─── Internals ───────────────────────────────────────────────────────

    private function boot(string $slug, ExtensionInterface $extension): bool
    {
        try {
            $extension->boot();
        } catch (Throwable $error) {
            // A broken extension must not take the site down with it.
            $this->fail($slug, $extension, [
                sprintf(
                    /* translators: %s: exception message. */
                    __('La extensión falló al iniciarse: %s', 'homlity-real-estate'),
                    $error->getMessage()
                ),
            ]);

            return false;
        }

        $this->booted[$slug] = $extension;

        /**
         * Fires right after an individual extension has booted.
         *
         * @since 2.8.0
         *
         * @param ExtensionInterface $extension The extension that just booted.
         * @param string             $slug      Its sanitized slug.
         */
        do_action(Hooks::EXTENSION_REGISTERED, $extension, $slug);

        return true;
    }

    /**
     * @param string[] $reasons
     */
    private function fail(string $key, ExtensionInterface $extension, array $reasons): void
    {
        $key = $key !== '' ? $key : 'unknown';

        $this->failures[$key] = $reasons;

        /**
         * Fires when an extension is refused or fails to boot.
         *
         * Use it to surface an admin notice, or to log the incompatibility.
         *
         * @since 2.8.0
         *
         * @param ExtensionInterface $extension The refused extension.
         * @param string[]           $reasons   Translated, human-readable reasons.
         * @param string             $slug      Its slug, or 'unknown' when unusable.
         */
        do_action(Hooks::EXTENSION_FAILED, $extension, $reasons, $key);
    }

    /**
     * @return string[]
     */
    private function unmetRequirements(ExtensionInterface $extension): array
    {
        try {
            $unmet = $extension->getRequirements()->unmetRequirements();
        } catch (Throwable $error) {
            return [
                sprintf(
                    /* translators: %s: exception message. */
                    __('No se pudieron leer los requisitos de la extensión: %s', 'homlity-real-estate'),
                    $error->getMessage()
                ),
            ];
        }

        /**
         * Filters whether an extension is considered compatible with this install.
         *
         * Returning true force-enables an extension whose declared requirements
         * are not met. Use it to unblock a staging site, never to paper over a
         * real incompatibility on production.
         *
         * @since 2.8.0
         *
         * @param bool               $isCompatible Whether every requirement is satisfied.
         * @param ExtensionInterface $extension    The extension being checked.
         * @param string[]           $unmet        Translated reasons, empty when compatible.
         */
        $isCompatible = (bool) apply_filters(
            Hooks::FILTER_EXTENSION_IS_COMPATIBLE,
            $unmet === [],
            $extension,
            $unmet
        );

        return $isCompatible ? [] : ($unmet !== [] ? $unmet : [
            __('La extensión fue marcada como incompatible.', 'homlity-real-estate'),
        ]);
    }

    private function readSlug(ExtensionInterface $extension): string
    {
        try {
            return sanitize_key($extension->getSlug());
        } catch (Throwable $error) {
            return '';
        }
    }

    private function readName(ExtensionInterface $extension): string
    {
        try {
            return sanitize_key($extension->getName());
        } catch (Throwable $error) {
            return '';
        }
    }
}
