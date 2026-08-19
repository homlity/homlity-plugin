<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\BotDetector;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use ReflectionProperty;

final class BotDetectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCache();
        unset($_SERVER['HTTP_USER_AGENT']);
    }

    protected function tearDown(): void
    {
        $this->resetCache();
        unset($_SERVER['HTTP_USER_AGENT']);
        parent::tearDown();
    }

    /** @dataProvider agentesDeBots */
    public function testDetectaBots(string $userAgent): void
    {
        self::assertTrue(BotDetector::isBot($userAgent));
    }

    /** @return array<string,array{0:string}> */
    public static function agentesDeBots(): array
    {
        return [
            'googlebot'   => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
            'bingbot'     => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'],
            'claudebot'   => ['Mozilla/5.0 (compatible; ClaudeBot/1.0)'],
            'curl'        => ['curl/8.4.0'],
            'python'      => ['python-requests/2.31.0'],
            'whatsapp'    => ['WhatsApp/2.23.20.0'],
            'facebook'    => ['facebookexternalhit/1.1'],
            'headless'    => ['Mozilla/5.0 HeadlessChrome/120.0.0.0'],
            'sin agente'  => [''],
        ];
    }

    /** @dataProvider agentesHumanos */
    public function testNoMarcaNavegadoresReales(string $userAgent): void
    {
        self::assertFalse(BotDetector::isBot($userAgent));
    }

    /** @return array<string,array{0:string}> */
    public static function agentesHumanos(): array
    {
        return [
            'chrome' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36'],
            'safari ios' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1'],
            'firefox' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:126.0) Gecko/20100101 Firefox/126.0'],
        ];
    }

    public function testUsaElUserAgentDeLaPeticionCuandoNoSeIndicaUno(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; SemrushBot/7~bl)';

        self::assertTrue(BotDetector::isBot());
    }

    public function testElResultadoDeLaPeticionSeCacheaDuranteElProceso(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'curl/8.4.0';
        self::assertTrue(BotDetector::isBot());

        // Cambiar el header no debe alterar el valor ya calculado.
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) Chrome/125.0 Safari/537.36';
        self::assertTrue(BotDetector::isBot());

        // Pero pasar un agente explícito siempre evalúa de nuevo.
        self::assertFalse(BotDetector::isBot($_SERVER['HTTP_USER_AGENT']));
    }

    private function resetCache(): void
    {
        $cached = new ReflectionProperty(BotDetector::class, 'cached');
        $cached->setAccessible(true);
        $cached->setValue(null, null);
    }
}
