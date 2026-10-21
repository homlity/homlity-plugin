<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Support;

/** Inventario mínimo compartido por las pruebas de calidad del texto público. */
trait PropertyQualityFixtures
{
    private function property(int $id, string $excerpt = 'Casa con jardín', array $meta = [], string $content = '', string $title = 'Inmueble'): \WP_Post
    {
        $post = new \WP_Post(['ID' => $id, 'post_type' => 'property', 'post_status' => 'publish',
            'post_title' => $title, 'post_excerpt' => $excerpt, 'post_content' => $content]);
        WpStubs::$postObjects[$id] = $post;
        WpStubs::setPost($id, $title, home_url('/inmueble-' . $id . '/'), $meta);
        WpStubs::$sqlResults['SELECT ID, post_excerpt, post_content'][] = [
            'ID' => $id, 'post_excerpt' => $excerpt, 'post_content' => $content,
        ];
        return $post;
    }

    private function assignTerm(int $id, string $taxonomy, string $name, string $slug = ''): void
    {
        $termId = count(WpStubs::$terms) + 1;
        $term = WpStubs::setTerm($termId, $taxonomy, $slug ?: sanitize_title($name), $name);
        WpStubs::$postTerms[$id][$taxonomy] = [$term];
    }

    private function physical(array $extra = []): array
    {
        return array_merge(['_property_address' => 'Calle 10 # 20-30', '_property_area' => '100',
            '_property_bedrooms' => '3', '_property_bathrooms' => '2'], $extra);
    }
}
