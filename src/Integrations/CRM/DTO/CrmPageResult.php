<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\DTO;

if (!defined('ABSPATH')) {
    exit;
}

class CrmPageResult
{
    /** @var array<int,array<string,mixed>> */
    private array $items;

    /** @var array<string,mixed> */
    private array $cursor;

    private bool $hasMore;

    /**
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed> $cursor
     */
    public function __construct(array $items, array $cursor = [], bool $hasMore = false)
    {
        $this->items = $items;
        $this->cursor = $cursor;
        $this->hasMore = $hasMore;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * @return array<string,mixed>
     */
    public function cursor(): array
    {
        return $this->cursor;
    }

    public function hasMore(): bool
    {
        return $this->hasMore;
    }
}
