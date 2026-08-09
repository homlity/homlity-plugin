<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Listing;

if (!defined('ABSPATH')) {
    exit;
}

final class PaginationWindow
{
    /**
     * @return array<int|string>
     */
    public static function items(int $currentPage, int $totalPages, int $radius = 2): array
    {
        $totalPages = max(1, $totalPages);
        $currentPage = min($totalPages, max(1, $currentPage));
        $radius = max(1, $radius);
        $windowSize = ($radius * 2) + 1;

        $start = max(1, $currentPage - $radius);
        $end = min($totalPages, $currentPage + $radius);

        if ($start === 1) {
            $end = min($totalPages, $windowSize);
        } elseif ($end === $totalPages) {
            $start = max(1, $totalPages - $windowSize + 1);
        }

        $items = [];
        if ($start > 1) {
            $items[] = 'ellipsis';
        }
        for ($page = $start; $page <= $end; $page++) {
            $items[] = $page;
        }
        if ($end < $totalPages) {
            $items[] = 'ellipsis';
        }

        return $items;
    }
}
