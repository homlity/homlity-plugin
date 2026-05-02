<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\DTO;

if (!defined('ABSPATH')) {
    exit;
}

class SchemaValidationResult
{
    /** @var array<int,array<string,string>> */
    private array $errors;

    /** @var array<int,array<string,string>> */
    private array $warnings;

    /**
     * @param array<int,array<string,string>> $errors
     * @param array<int,array<string,string>> $warnings
     */
    public function __construct(array $errors = [], array $warnings = [])
    {
        $this->errors = $errors;
        $this->warnings = $warnings;
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /** @return array<int,array<string,string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<int,array<string,string>> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
