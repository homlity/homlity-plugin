<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\DTO;

if (!defined('ABSPATH')) {
    exit;
}

class IntegrationTestResult
{
    private bool $ok;
    private string $message;

    /** @var array<string,mixed> */
    private array $context;

    /**
     * @param array<string,mixed> $context
     */
    public function __construct(bool $ok, string $message = '', array $context = [])
    {
        $this->ok = $ok;
        $this->message = $message;
        $this->context = $context;
    }

    public function ok(): bool
    {
        return $this->ok;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string,mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
