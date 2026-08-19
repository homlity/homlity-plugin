<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations\CRM\Services;

use Homlity\PluginInmobiliario\Integrations\CRM\Services\WebhookAuthenticator;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use WP_REST_Request;

final class WebhookAuthenticatorTest extends TestCase
{
    private WebhookAuthenticator $authenticator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticator = new WebhookAuthenticator();
    }

    public function testAceptaLaClaveEnLaCabeceraDedicada(): void
    {
        $request = new WP_REST_Request(['x-homlity-integration-key' => 'clave-secreta']);

        self::assertTrue($this->authenticator->verify($request, ['webhook_key' => 'clave-secreta']));
    }

    public function testAceptaLaClaveComoBearer(): void
    {
        $request = new WP_REST_Request(['authorization' => 'Bearer clave-secreta']);

        self::assertTrue($this->authenticator->verify($request, ['webhook_key' => 'clave-secreta']));
    }

    public function testRechazaUnaClaveIncorrecta(): void
    {
        $request = new WP_REST_Request(['x-homlity-integration-key' => 'otra']);

        self::assertFalse($this->authenticator->verify($request, ['webhook_key' => 'clave-secreta']));
    }

    public function testRechazaUnEsquemaDeAutorizacionDistinto(): void
    {
        $request = new WP_REST_Request(['authorization' => 'Basic clave-secreta']);

        self::assertFalse($this->authenticator->verify($request, ['webhook_key' => 'clave-secreta']));
    }

    public function testRechazaCuandoNoHayCredencialesConfiguradas(): void
    {
        $request = new WP_REST_Request(['x-homlity-integration-key' => 'clave-secreta']);

        self::assertFalse($this->authenticator->verify($request, []));
    }

    public function testAceptaUnaFirmaHmacValida(): void
    {
        $request = $this->signedRequest('{"event":"property.updated"}', 'secreto-hmac', time());

        self::assertTrue($this->authenticator->verify($request, ['webhook_hmac_secret' => 'secreto-hmac']));
    }

    public function testRechazaUnaFirmaHmacCalculadaSobreOtroCuerpo(): void
    {
        $request = $this->signedRequest('{"event":"property.updated"}', 'secreto-hmac', time());
        $request->set_body('{"event":"property.deleted"}');

        self::assertFalse($this->authenticator->verify($request, ['webhook_hmac_secret' => 'secreto-hmac']));
    }

    public function testRechazaUnaFirmaFueraDeLaVentanaDeTolerancia(): void
    {
        $request = $this->signedRequest('{}', 'secreto-hmac', time() - 900);

        self::assertFalse($this->authenticator->verify($request, [
            'webhook_hmac_secret' => 'secreto-hmac',
            'webhook_tolerance'   => 300,
        ]));
    }

    public function testAplicaUnaToleranciaMinimaDe30Segundos(): void
    {
        $request = $this->signedRequest('{}', 'secreto-hmac', time() - 10);

        self::assertTrue($this->authenticator->verify($request, [
            'webhook_hmac_secret' => 'secreto-hmac',
            'webhook_tolerance'   => 0,
        ]));
    }

    /** @dataProvider cabecerasHmacInvalidas */
    public function testRechazaCabecerasHmacMalFormadas(string $signature, string $timestamp): void
    {
        $request = new WP_REST_Request([
            'x-homlity-signature' => $signature,
            'x-homlity-timestamp' => $timestamp,
        ], '{}');

        self::assertFalse($this->authenticator->verify($request, ['webhook_hmac_secret' => 'secreto-hmac']));
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function cabecerasHmacInvalidas(): array
    {
        return [
            'sin firma'            => ['', (string) 1750000000],
            'sin timestamp'        => ['sha256=abc', ''],
            'timestamp no numerico' => ['sha256=abc', 'ayer'],
        ];
    }

    public function testElHmacInvalidoNoImpideLaValidacionPorClave(): void
    {
        $request = new WP_REST_Request([
            'x-homlity-signature'        => 'sha256=falsa',
            'x-homlity-timestamp'        => (string) time(),
            'x-homlity-integration-key'  => 'clave-secreta',
        ], '{}');

        self::assertTrue($this->authenticator->verify($request, [
            'webhook_hmac_secret' => 'secreto-hmac',
            'webhook_key'         => 'clave-secreta',
        ]));
    }

    private function signedRequest(string $body, string $secret, int $timestamp): WP_REST_Request
    {
        return new WP_REST_Request([
            'x-homlity-signature' => 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret),
            'x-homlity-timestamp' => (string) $timestamp,
        ], $body);
    }
}
