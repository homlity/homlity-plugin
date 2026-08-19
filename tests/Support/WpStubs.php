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

    /** @var array<int,\WP_User> user id => usuario */
    public static array $users = [];

    /** @var array<int,array<string,mixed>> user id => [metaKey => value] */
    public static array $userMeta = [];

    /** Si la petición actual es un archivo de autor. */
    public static bool $isAuthor = false;

    /** Post type de is_singular(); vacío = la petición no es singular. */
    public static string $singularPostType = '';

    /** Post type de is_post_type_archive(); vacío = no es un archivo. */
    public static string $postTypeArchive = '';

    /** Página de is_page(); 0 = la petición no es una página. */
    public static int $currentPageId = 0;

    /** Post dentro del bucle: lo que devuelve get_the_ID(). */
    public static int $currentPostId = 0;

    /** Taxonomía de is_tax(); vacío = no es un archivo de término. */
    public static string $currentTaxonomy = '';

    /**
     * Resuelve los WP_Query que construye el código bajo prueba.
     *
     * @var null|callable(array<string,mixed>):array{posts?:array<int,mixed>,found_posts?:int}
     */
    public static $queryResolver = null;

    /** @var array<int,array{location:string,status:int}> redirecciones emitidas */
    public static array $redirects = [];

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
        self::$users = [];
        self::$userMeta = [];
        self::$isAuthor = false;
        self::$singularPostType = '';
        self::$postTypeArchive = '';
        self::$currentPageId = 0;
        self::$currentPostId = 0;
        self::$currentTaxonomy = '';
        self::$queryResolver = null;
        self::$redirects = [];
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
     * Registers a user so get_user_by()/get_userdata() can find it.
     *
     * @param array<string,mixed> $fields
     * @param string[]            $roles
     * @param array<string,mixed> $meta
     */
    public static function setUser(int $id, string $nicename, array $fields = [], array $roles = [], array $meta = []): \WP_User
    {
        $user = new \WP_User(array_merge([
            'ID' => $id,
            'user_nicename' => $nicename,
            'display_name' => $nicename,
            'user_email' => '',
            'user_url' => '',
            'roles' => $roles,
        ], $fields));

        self::$users[$id] = $user;
        self::$userMeta[$id] = $meta;

        return $user;
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
