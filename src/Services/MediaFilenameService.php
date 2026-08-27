<?php
/**
 * Prevents image optimizer output files from colliding across source formats.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class MediaFilenameService implements ServiceInterface
{
    /** @var list<string> */
    private const IMAGE_EXTENSIONS = [
        'jpg',
        'jpeg',
        'jpe',
        'png',
        'gif',
        'webp',
        'avif',
        'heic',
        'heif',
    ];

    public function register(): void
    {
        add_filter('wp_unique_filename', [$this, 'avoidCrossExtensionCollision'], 20, 6);
    }

    /**
     * Makes the basename unique across every image extension, not only the
     * source extension checked by WordPress core.
     *
     * Image optimizers commonly turn both `photo.jpg` and `photo.webp` into
     * `photo.avif`/`photo.webp`. Without this extra check, the second upload can
     * overwrite the optimized files — or even the original WebP — of the first.
     *
     * @param callable|null $uniqueFilenameCallback
     * @param array<string,string> $alternateFilenames
     * @param int|string $number
     */
    public function avoidCrossExtensionCollision(
        string $filename,
        string $extension,
        string $directory,
        $uniqueFilenameCallback = null,
        array $alternateFilenames = [],
        $number = ''
    ): string {
        unset($extension, $uniqueFilenameCallback, $alternateFilenames, $number);

        $sourceExtension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($sourceExtension, self::IMAGE_EXTENSIONS, true) || !is_dir($directory)) {
            return $filename;
        }

        $stem = (string) pathinfo($filename, PATHINFO_FILENAME);

        if ($stem === '' || !$this->basenameExists($directory, $stem)) {
            return $filename;
        }

        $suffix = 2;

        do {
            $candidateStem = $stem . '-homlity-' . $suffix;
            $suffix++;
        } while ($this->basenameExists($directory, $candidateStem));

        return $candidateStem . '.' . $sourceExtension;
    }

    private function basenameExists(string $directory, string $stem): bool
    {
        $directory = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR;

        foreach (self::IMAGE_EXTENSIONS as $extension) {
            if (file_exists($directory . $stem . '.' . $extension)
                || file_exists($directory . $stem . '.' . strtoupper($extension))) {
                return true;
            }
        }

        return false;
    }
}
