<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\DTO;

if (!defined('ABSPATH')) {
    exit;
}

class NormalizedProperty
{
    /** @var array<string,mixed> */
    private array $data;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    public function sourceKey(): string
    {
        return sanitize_key((string) ($this->data['source_key'] ?? ''));
    }

    public function externalId(): string
    {
        return sanitize_text_field((string) ($this->data['external_id'] ?? ''));
    }

    public function externalHash(): string
    {
        return sanitize_text_field((string) ($this->data['external_hash'] ?? ''));
    }
}
