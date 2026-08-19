<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Support;

/**
 * Estado mutable que respaldan los stubs de funciones de WordPress
 * definidos en tests/Support/wp-functions.php.
 *
 * Cada prueba parte de un estado limpio gracias a TestCase::setUp().
 */
final class WpStubs
{
    /** @var array<string,mixed> */
    public static array $options = [];

    /** @var array<int,array<string,mixed>> postId => [metaKey => value] */
    public static array $postMeta = [];

    /** @var array<int,string> postId => título */
    public static array $postTitles = [];

    /** @var array<int,string> postId => permalink */
    public static array $permalinks = [];

    /** @var string[] post types registrados */
    public static array $postTypes = [];

    /** @var array<int,string> post id => post_status */
    public static array $postStatuses = [];

    /** @var array<int,object> post id => WP_Post-like object */
    public static array $postObjects = [];

    public static string $homeUrl = 'https://example.test';

    /** @var array<string,array<int,object>> taxonomy => term id => WP_Term */
    public static array $terms = [];

    /** @var array<int,array<string,mixed>> term id => meta */
    public static array $termMeta = [];

    /** @var array<int,int[]> locality id => neighborhood term ids */
    public static array $localityNeighborhoods = [];

    /** @var array<string,mixed> WordPress query vars */
    public static array $queryVars = [];

    /** Object returned by get_queried_object(). */
    public static ?object $queriedObject = null;

    public static bool $isSearch = false;

    public static string $searchQuery = '';

    /** @var array<int,object> resultado que devolverá get_posts() */
    public static array $posts = [];

    /** @var array<int,array<string,mixed>> argumentos recibidos por get_posts() */
    public static array $getPostsCalls = [];

    /** @var array<string,array<int,callable>> hook => callbacks */
    public static array $filters = [];

    /** @var array<string,array<int,array<string,mixed>>> hook => llamadas a do_action() */
    public static array $actions = [];

    /** @var array<int,array{hook:string,group:string}> acciones del store de Action Scheduler */
    public static array $scheduledActions = [];

    /** @var array<string,int> hook => timestamp de la próxima ejecución (WP-Cron) */
    public static array $cronEvents = [];

    public static string $locale = 'es_CO';

    public static function reset(): void
    {
        self::$options = [];
        self::$postMeta = [];
        self::$postTitles = [];
        self::$permalinks = [];
        self::$postTypes = [];
        self::$postStatuses = [];
        self::$postObjects = [];
        self::$homeUrl = 'https://example.test';
        self::$terms = [];
        self::$termMeta = [];
        self::$localityNeighborhoods = [];
        self::$queryVars = [];
        self::$queriedObject = null;
        self::$isSearch = false;
        self::$searchQuery = '';
        self::$posts = [];
        self::$getPostsCalls = [];
        self::$filters = [];
        self::$actions = [];
        self::$scheduledActions = [];
        self::$cronEvents = [];
        self::$locale = 'es_CO';
    }

    /**
     * Registers a term so term_exists()/get_term() can find it.
     */
    public static function setTerm(int $termId, string $taxonomy, string $slug = '', string $name = ''): object
    {
        $term = new \WP_Term($termId, $taxonomy, $slug !== '' ? $slug : 'term-' . $termId, $name !== '' ? $name : 'Term ' . $termId);
        self::$terms[$taxonomy][$termId] = $term;

        return $term;
    }

    /** @param array<string,mixed> $meta */
    public static function setPost(int $postId, string $title = '', string $permalink = '', array $meta = []): void
    {
        self::$postTitles[$postId] = $title;
        self::$permalinks[$postId] = $permalink;
        self::$postMeta[$postId] = $meta;
    }

    /** @param array<string,mixed> $meta */
    public static function setPostMeta(int $postId, array $meta): void
    {
        self::$postMeta[$postId] = array_merge(self::$postMeta[$postId] ?? [], $meta);
    }

    /** @param array<string,mixed> $value */
    public static function setOption(string $key, $value): void
    {
        self::$options[$key] = $value;
    }

    public static function addFilter(string $hook, callable $callback): void
    {
        self::$filters[$hook][] = $callback;
    }

    /**
     * Registra una acción en el store simulado de Action Scheduler.
     */
    public static function setScheduledAction(int $actionId, string $hook, string $group = ''): void
    {
        self::$scheduledActions[$actionId] = ['hook' => $hook, 'group' => $group];
    }

    /**
     * Crea un objeto tipo WP_Post mínimo para get_posts().
     *
     * @param array<string,mixed> $meta
     */
    public static function makePost(int $postId, array $meta = []): object
    {
        self::setPostMeta($postId, $meta);

        return (object) ['ID' => $postId, 'post_type' => 'post', 'post_status' => 'publish'];
    }
}
