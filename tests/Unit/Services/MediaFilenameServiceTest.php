<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\MediaFilenameService;
use PHPUnit\Framework\TestCase;

final class MediaFilenameServiceTest extends TestCase
{
    private string $uploadDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uploadDirectory = sys_get_temp_dir() . '/homlity-media-' . bin2hex(random_bytes(6));
        mkdir($this->uploadDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->uploadDirectory . '/*') as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->uploadDirectory)) {
            rmdir($this->uploadDirectory);
        }

        parent::tearDown();
    }

    public function testKeepsFilenameWhenNoOtherFormatUsesItsBasename(): void
    {
        self::assertSame('1-2.jpg', $this->filter('1-2.jpg'));
    }

    public function testRenamesJpegWhenWebpAlreadyUsesTheBasename(): void
    {
        touch($this->uploadDirectory . '/1-2.webp');

        self::assertSame('1-2-homlity-2.jpg', $this->filter('1-2.jpg'));
    }

    public function testSkipsSuffixesAlreadyUsedByOptimizerOutputs(): void
    {
        touch($this->uploadDirectory . '/1-2.webp');
        touch($this->uploadDirectory . '/1-2-homlity-2.avif');

        self::assertSame('1-2-homlity-3.jpg', $this->filter('1-2.jpg'));
    }

    public function testDoesNotRenameNonImageFiles(): void
    {
        touch($this->uploadDirectory . '/brochure.jpg');

        self::assertSame('brochure.pdf', $this->filter('brochure.pdf'));
    }

    private function filter(string $filename): string
    {
        return (new MediaFilenameService())->avoidCrossExtensionCollision(
            $filename,
            '.' . pathinfo($filename, PATHINFO_EXTENSION),
            $this->uploadDirectory
        );
    }
}
