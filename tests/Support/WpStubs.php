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

    /** Portada estática: lo que devuelve is_front_page(). */
    public static bool $isFrontPage = false;

    /** Listado de entradas: lo que devuelve is_home(). */
    public static bool $isHome = false;

    /** @var array<int,string> Capacidades que current_user_can() concede. */
    public static array $capabilities = [];

    /** Si es false, check_admin_referer() corta con wp_die(). */
    public static bool $nonceValid = true;

    /** @var array<int,array{action:string,field:string}> Nonces comprobados. */
    public static array $checkedNonces = [];

    /** Post type de is_singular(); vacío = la petición no es singular. */
    public static string $singularPostType = '';

    /** Post type de is_post_type_archive(); vacío = no es un archivo. */
    public static string $postTypeArchive = '';

    /** Página de is_page(); 0 = la petición no es una página. */
    public static int $currentPageId = 0;

    /** Post dentro del bucle: lo que devuelve get_the_ID(). */
    public static int $currentPostId = 0;

    /** @var array<int,array<string,array<int,object>>> post id => taxonomía => términos */
    public static array $postTerms = [];

    /**
     * Taxonomías que hacen fallar a wp_get_post_terms().
     *
     * WordPress devuelve WP_Error cuando la taxonomía no está registrada, y el
     * código que la consulta tiene que distinguir eso de "no hay términos".
     *
     * @var array<string,string> taxonomía => mensaje de error
     */
    public static array $postTermsError = [];

    /** @var array<int,string> post id => contenido del post */
    public static array $postContent = [];

    /** @var array<int,string> post id => extracto del post */
    public static array $postExcerpt = [];

    /** @var array<int,string> post id => URL de la imagen destacada */
    public static array $thumbnails = [];

    /** @var array<int,array{0:int,1:int}> adjunto => [ancho, alto] */
    public static array $attachmentSizes = [];

    /** Como el ajuste «Mostrar avatares» apagado: get_avatar_url() no da nada. */
    public static bool $avatarsDisabled = false;

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

    /** @var array<int,string> */
    public static array $postExcerpts = [];

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

    /** @var array<int,string> Taxonomías registradas: lo que ve taxonomy_exists(). */
    public static array $registeredTaxonomies = [];

    /** @var array<int,array<string,array<int,int>>> post id => taxonomía => term ids asignados */
    public static array $objectTerms = [];

    /** Último term id repartido por wp_insert_term(). */
    public static int $nextTermId = 1000;

    /** @var array<string,mixed> Caché de objetos: clave "grupo|clave" => valor. */
    public static array $cache = [];

    /** @var array<int,string> Roles registrados: lo que ve get_role(). */
    public static array $registeredRoles = [];

    /** Último id repartido por wp_insert_user(). */
    public static int $nextUserId = 500;

    /** Último id repartido por wp_insert_post(). */
    public static int $nextPostId = 100;

    /** Si no está vacío, wp_insert_post() falla con este mensaje. */
    public static string $postInsertError = '';

    /** @var array<string,mixed> Ajustes del tema: lo que ve get_theme_mod(). */
    public static array $themeMods = [];

    /** @var array<int,string> id de adjunto => URL; cadena vacía = adjunto borrado. */
    public static array $attachmentUrls = [];

    /** @var array<int,string> Tablas propias que existen: lo que ve SHOW TABLES LIKE. */
    public static array $existingTables = [];

    /** Petición sin resultado: lo que devuelve is_404(). */
    public static bool $is404 = false;

    /** @var list<int> Códigos HTTP emitidos con status_header(). */
    public static array $statusHeaders = [];

    /** @var array<string,array<string,mixed>> Lo que se pasó a wp_enqueue_script(). */
    public static array $enqueuedScripts = [];

    /**
     * Filas fijadas para consultas que el motor en memoria no sabe leer,
     * indexadas por una subcadena de la consulta.
     *
     * El motor lanza una excepción ante cualquier SQL que no entienda, y eso
     * es a propósito: devolver un array vacío convertiría cada prueba en un
     * falso positivo. Pero hay consultas —un JOIN contra las metas de los
     * inmuebles, por ejemplo— que no compensa enseñarle a ejecutar. Fijar aquí
     * su resultado deja probar lo que se hace con las filas sin fingir que la
     * consulta se ejecutó, y sin abrir la mano para el resto.
     *
     * @var array<string,list<array<string,mixed>>>
     */
    public static array $sqlResults = [];

    /** Directorio del tema activo, para las plantillas sobrescribibles. */
    public static string $stylesheetDirectory = '/tmp/homlity-tema-inexistente';

    /** Pantalla de administración: lo que devuelve is_admin(). */
    public static bool $isAdminScreen = false;

    /** Petición AJAX en curso: lo que devuelve wp_doing_ajax(). */
    public static bool $doingAjax = false;

    /** Visitante identificado: lo que devuelve is_user_logged_in(). */
    public static bool $userLoggedIn = false;

    /** Si es true, no se pueden enviar cookies. */
    public static bool $headersSent = false;

    /** @var array<string,array{value:string,options:array|int}> Cookies emitidas. */
    public static array $cookiesSet = [];

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

    /** @var array<string,string> gancho => periodicidad con la que se programó */
    public static array $cronRecurrences = [];

    /** @var list<array{hook:string,recurrence:string,timestamp:int}> Cada llamada a wp_schedule_event(). */
    public static array $scheduleCalls = [];

    public static string $locale = 'es_CO';

    public static function reset(): void
    {
        self::$options = [];
        self::$users = [];
        self::$userMeta = [];
        self::$isAuthor = false;
        self::$isFrontPage = false;
        self::$isHome = false;
        self::$capabilities = [];
        self::$nonceValid = true;
        self::$checkedNonces = [];
        self::$singularPostType = '';
        self::$postTypeArchive = '';
        self::$currentPageId = 0;
        self::$currentPostId = 0;
        self::$postTerms = [];
        self::$postTermsError = [];
        self::$postContent = [];
        self::$postExcerpt = [];
        self::$thumbnails = [];
        self::$attachmentSizes = [];
        self::$avatarsDisabled = false;
        self::$currentTaxonomy = '';
        self::$queryResolver = null;
        self::$redirects = [];
        self::$postMeta = [];
        self::$postTitles = [];
        self::$postExcerpts = [];
        self::$permalinks = [];
        self::$postTypes = [];
        self::$postStatuses = [];
        self::$postObjects = [];
        self::$homeUrl = 'https://example.test';
        self::$terms = [];
        self::$registeredTaxonomies = [];
        self::$objectTerms = [];
        self::$nextTermId = 1000;
        self::$cache = [];
        self::$registeredRoles = [];
        self::$nextUserId = 500;
        self::$nextPostId = 100;
        self::$postInsertError = '';
        self::$themeMods = [];
        self::$attachmentUrls = [];
        self::$existingTables = [];
        self::$is404 = false;
        self::$statusHeaders = [];
        self::$enqueuedScripts = [];
        self::$sqlResults = [];
        self::$stylesheetDirectory = '/tmp/homlity-tema-inexistente';
        self::$isAdminScreen = false;
        self::$doingAjax = false;
        self::$userLoggedIn = false;
        self::$headersSent = false;
        self::$cookiesSet = [];
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
        self::$cronRecurrences = [];
        self::$scheduleCalls = [];
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

    /**
     * Asigna términos a un post, como los devolvería wp_get_post_terms().
     *
     * @param array<int,int> $termIds
     */
    public static function setPostTerms(int $postId, string $taxonomy, array $termIds): void
    {
        self::$postTerms[$postId][$taxonomy] = array_map(
            static fn(int $id): object => self::setTerm($id, $taxonomy),
            $termIds
        );
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
