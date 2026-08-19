<?php

declare(strict_types=1);

/**
 * Stubs de las funciones y clases de WordPress usadas por el plugin.
 *
 * Permiten ejecutar pruebas unitarias reales (sin instalar WordPress ni una
 * base de datos). El estado vive en WpStubs y se reinicia en cada prueba.
 */

use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        /** @var array<string,array<int,string>> */
        private array $errors = [];

        /** @param mixed $data */
        public function __construct(private string $code = '', private string $message = '', private $data = null)
        {
            if ($code !== '') {
                $this->errors[$code][] = $message;
            }
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        /** @return mixed */
        public function get_error_data()
        {
            return $this->data;
        }
    }
}

if (!class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID = 0;
        public string $post_type = 'post';
        public string $post_name = '';
        public string $post_status = 'publish';
        public string $post_title = '';
        public string $post_content = '';

        /** @param array<string,mixed> $data */
        public function __construct(array $data = [])
        {
            foreach ($data as $key => $value) {
                if (property_exists($this, (string) $key)) {
                    $this->$key = $value;
                }
            }
        }
    }
}

if (!class_exists('WP_Query')) {
    class WP_Query
    {
        /** @var array<int,mixed> */
        public array $posts = [];

        public int $found_posts = 0;

        /** @param array<string,mixed> $query_vars */
        public function __construct(public array $query_vars = [])
        {
            // Sin resolver registrado la consulta no devuelve nada: las
            // pruebas que sólo inspeccionan los argumentos no tienen que
            // preparar resultados.
            $resolver = WpStubs::$queryResolver;
            if (is_callable($resolver)) {
                $result = $resolver($query_vars);
                $this->posts = (array) ($result['posts'] ?? []);
                $this->found_posts = (int) ($result['found_posts'] ?? count($this->posts));
            }
        }

        public function get(string $var, mixed $default = '')
        {
            return $this->query_vars[$var] ?? $default;
        }
    }
}

if (!class_exists('HomlityTestWpdb')) {
    /**
     * Enough of $wpdb for the SQL the search service composes: the tests
     * assert on the generated clauses, so prepare() substitutes literally
     * instead of pretending to escape.
     */
    class HomlityTestWpdb
    {
        public string $prefix = 'wp_';
        public string $posts = 'wp_posts';
        public string $postmeta = 'wp_postmeta';
        public string $terms = 'wp_terms';
        public string $term_taxonomy = 'wp_term_taxonomy';
        public string $term_relationships = 'wp_term_relationships';

        public function esc_like(string $text): string
        {
            return addcslashes($text, '_%\\');
        }

        public function prepare(string $query, mixed ...$args): string
        {
            $query = str_replace(['%s', '%d'], ["'%s'", '%d'], $query);

            return vsprintf($query, array_map(static fn($a): string => (string) $a, $args));
        }
    }
}

if (!function_exists('esc_sql')) {
    function esc_sql(string $value): string
    {
        return addslashes($value);
    }
}

if (!class_exists('WP_Term')) {
    class WP_Term
    {
        public function __construct(
            public int $term_id = 0,
            public string $taxonomy = '',
            public string $slug = '',
            public string $name = '',
            public int $parent = 0
        ) {
        }
    }
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        /** @var array<string,string> */
        private array $headers = [];

        private string $body = '';

        /** @var array<string,mixed> */
        private array $params = [];

        /** @param array<string,string> $headers */
        public function __construct(array $headers = [], string $body = '', array $params = [])
        {
            foreach ($headers as $key => $value) {
                $this->headers[strtolower($key)] = $value;
            }
            $this->body = $body;
            $this->params = $params;
        }

        public function get_header(string $key): ?string
        {
            return $this->headers[strtolower(str_replace('_', '-', $key))] ?? null;
        }

        public function set_header(string $key, string $value): void
        {
            $this->headers[strtolower(str_replace('_', '-', $key))] = $value;
        }

        public function get_body(): string
        {
            return $this->body;
        }

        public function set_body(string $body): void
        {
            $this->body = $body;
        }

        /** @return mixed */
        public function get_param(string $key)
        {
            return $this->params[$key] ?? null;
        }

        /** @return array<string,mixed> */
        public function get_params(): array
        {
            return $this->params;
        }
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e(string $text, string $domain = 'default'): void
    {
        echo $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return esc_html($text);
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        return strtolower((string) preg_replace('/[^a-zA-Z0-9_\-]/', '', $key));
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim((string) preg_replace('/[\r\n\t ]+/', ' ', strip_tags($value)));
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $text, bool $removeBreaks = false): string
    {
        $text = (string) preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $text);
        $text = strip_tags($text);

        return $removeBreaks ? trim((string) preg_replace('/[\r\n\t ]+/', ' ', $text)) : trim($text);
    }
}

if (!function_exists('wp_unslash')) {
    /**
     * @param mixed $value
     * @return mixed
     */
    function wp_unslash($value)
    {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('wp_check_invalid_utf8')) {
    function wp_check_invalid_utf8(string $value, bool $strip = false): string
    {
        return $value;
    }
}

if (!function_exists('wp_parse_url')) {
    /** @return mixed */
    function wp_parse_url(string $url, int $component = -1)
    {
        return parse_url($url, $component);
    }
}

if (!function_exists('wp_json_encode')) {
    /**
     * @param mixed $value
     * @return string|false
     */
    function wp_json_encode($value, int $flags = 0, int $depth = 512)
    {
        return json_encode($value, $flags, $depth);
    }
}

if (!function_exists('number_format_i18n')) {
    function number_format_i18n(float $number, int $decimals = 0): string
    {
        return number_format($number, $decimals, ',', '.');
    }
}

if (!function_exists('get_locale')) {
    function get_locale(): string
    {
        return WpStubs::$locale;
    }
}

if (!function_exists('get_option')) {
    /**
     * @param mixed $default
     * @return mixed
     */
    function get_option(string $key, $default = false)
    {
        return array_key_exists($key, WpStubs::$options) ? WpStubs::$options[$key] : $default;
    }
}

if (!function_exists('update_option')) {
    /** @param mixed $value */
    function update_option(string $key, $value, $autoload = null): bool
    {
        WpStubs::$options[$key] = $value;

        return true;
    }
}

if (!function_exists('add_option')) {
    /** @param mixed $value */
    function add_option(string $key, $value = '', string $deprecated = '', $autoload = null): bool
    {
        if (array_key_exists($key, WpStubs::$options)) {
            return false;
        }
        WpStubs::$options[$key] = $value;

        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $key): bool
    {
        $existed = array_key_exists($key, WpStubs::$options);
        unset(WpStubs::$options[$key]);

        return $existed;
    }
}

if (!function_exists('get_post_meta')) {
    /** @return mixed */
    function get_post_meta(int $postId, string $key = '', bool $single = false)
    {
        $meta = WpStubs::$postMeta[$postId] ?? [];
        if ($key === '') {
            return $meta;
        }
        if (!array_key_exists($key, $meta)) {
            return $single ? '' : [];
        }

        return $single ? $meta[$key] : [$meta[$key]];
    }
}

if (!function_exists('update_post_meta')) {
    /** @param mixed $value */
    function update_post_meta(int $postId, string $key, $value): bool
    {
        WpStubs::$postMeta[$postId][$key] = $value;

        return true;
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title(int $postId = 0): string
    {
        return WpStubs::$postTitles[$postId] ?? '';
    }
}

if (!function_exists('get_permalink')) {
    /** @return string|false */
    function get_permalink(int $postId = 0)
    {
        return WpStubs::$permalinks[$postId] ?? false;
    }
}

if (!function_exists('post_type_exists')) {
    function post_type_exists(string $postType): bool
    {
        return in_array($postType, WpStubs::$postTypes, true);
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $value): bool
    {
        return $value instanceof WP_Error;
    }
}

if (!function_exists('remove_accents')) {
    function remove_accents(string $value): string
    {
        return strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U',
        ]);
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(remove_accents($value))), '-');
    }
}

// ── Taxonomies ───────────────────────────────────────────────────────────────

if (!function_exists('term_exists')) {
    /** @return array{term_id:int}|null */
    function term_exists(int|string $term, string $taxonomy = ''): ?array
    {
        $termId = (int) $term;
        if ($taxonomy !== '') {
            return isset(WpStubs::$terms[$taxonomy][$termId]) ? ['term_id' => $termId] : null;
        }
        foreach (WpStubs::$terms as $terms) {
            if (isset($terms[$termId])) {
                return ['term_id' => $termId];
            }
        }

        return null;
    }
}

if (!function_exists('get_term')) {
    function get_term(int $termId, string $taxonomy = ''): ?WP_Term
    {
        if ($taxonomy !== '') {
            return WpStubs::$terms[$taxonomy][$termId] ?? null;
        }
        foreach (WpStubs::$terms as $terms) {
            if (isset($terms[$termId])) {
                return $terms[$termId];
            }
        }

        return null;
    }
}

if (!function_exists('get_term_by')) {
    function get_term_by(string $field, string|int $value, string $taxonomy = ''): WP_Term|false
    {
        foreach (WpStubs::$terms[$taxonomy] ?? [] as $term) {
            if ($field === 'slug' && $term->slug === (string) $value) {
                return $term;
            }
            if ($field === 'name' && $term->name === (string) $value) {
                return $term;
            }
            if ($field === 'id' && $term->term_id === (int) $value) {
                return $term;
            }
        }

        return false;
    }
}

if (!function_exists('get_term_meta')) {
    function get_term_meta(int $termId, string $key = '', bool $single = false): mixed
    {
        $meta = WpStubs::$termMeta[$termId] ?? [];
        if ($key === '') {
            return $meta;
        }

        return $meta[$key] ?? '';
    }
}

if (!function_exists('get_terms')) {
    /**
     * Only the shape LocalityPostType::neighborhoodIds() asks for is modelled:
     * neighbourhood ids linked to a locality through term meta.
     *
     * @param array<string,mixed> $args
     * @return list<int>
     */
    function get_terms(array $args = []): array
    {
        foreach ((array) ($args['meta_query'] ?? []) as $clause) {
            if (($clause['key'] ?? '') === '_parent_locality') {
                return WpStubs::$localityNeighborhoods[(int) ($clause['value'] ?? 0)] ?? [];
            }
        }

        return array_keys(WpStubs::$terms[$args['taxonomy'] ?? ''] ?? []);
    }
}

// ── Request context ──────────────────────────────────────────────────────────

if (!function_exists('get_query_var')) {
    function get_query_var(string $var, mixed $default = ''): mixed
    {
        return WpStubs::$queryVars[$var] ?? $default;
    }
}

if (!function_exists('is_search')) {
    function is_search(): bool
    {
        return WpStubs::$isSearch;
    }
}

if (!function_exists('get_search_query')) {
    function get_search_query(bool $escaped = true): string
    {
        return WpStubs::$searchQuery;
    }
}

if (!function_exists('get_queried_object')) {
    function get_queried_object(): ?object
    {
        return WpStubs::$queriedObject;
    }
}

if (!function_exists('get_page_by_path')) {
    function get_page_by_path(string $path, string $output = 'OBJECT', string $postType = 'post'): ?object
    {
        foreach (WpStubs::$postObjects as $post) {
            if (($post->post_name ?? '') === $path && ($post->post_type ?? '') === $postType) {
                return $post;
            }
        }

        return null;
    }
}

if (!function_exists('get_post_status')) {
    /** @return string|false */
    function get_post_status(int $postId = 0)
    {
        return WpStubs::$postStatuses[$postId] ?? false;
    }
}

if (!function_exists('get_post')) {
    /** @return object|null */
    function get_post(int $postId = 0)
    {
        return WpStubs::$postObjects[$postId] ?? null;
    }
}

if (!function_exists('get_post_type')) {
    /** @return string|false */
    function get_post_type(int $postId = 0)
    {
        $post = WpStubs::$postObjects[$postId] ?? null;

        return $post !== null ? (string) $post->post_type : false;
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return rtrim(WpStubs::$homeUrl, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    /**
     * @param string|array<string,string|int> $key
     * @param mixed $value
     */
    function add_query_arg($key, $value = null, string $url = ''): string
    {
        $args = is_array($key) ? $key : [$key => $value];
        if (is_array($key)) {
            $url = is_string($value) ? $value : '';
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        $pairs = [];
        foreach ($args as $name => $argValue) {
            $pairs[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $argValue);
        }

        return $url . $separator . implode('&', $pairs);
    }
}

if (!function_exists('get_posts')) {
    /**
     * @param array<string,mixed> $args
     * @return array<int,object>
     */
    function get_posts(array $args = []): array
    {
        WpStubs::$getPostsCalls[] = $args;

        return array_shift(WpStubs::$posts) ?: [];
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        WpStubs::$filters[$hook][] = $callback;

        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        WpStubs::$filters[$hook][] = $callback;

        return true;
    }
}

if (!function_exists('apply_filters')) {
    /**
     * @param mixed $value
     * @return mixed
     */
    function apply_filters(string $hook, $value = null, ...$args)
    {
        foreach (WpStubs::$filters[$hook] ?? [] as $callback) {
            $value = $callback($value, ...$args);
        }

        return $value;
    }
}

if (!function_exists('apply_filters_ref_array')) {
    /**
     * @param array<int,mixed> $args
     * @return mixed
     */
    function apply_filters_ref_array(string $hook, array $args)
    {
        $value = $args[0] ?? null;
        $rest = array_slice($args, 1);
        foreach (WpStubs::$filters[$hook] ?? [] as $callback) {
            $value = $callback($value, ...$rest);
        }

        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action(string $hook, ...$args): void
    {
        WpStubs::$actions[$hook][] = $args;
        foreach (WpStubs::$filters[$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}

if (!function_exists('has_filter')) {
    function has_filter(string $hook, $callback = false): bool
    {
        return !empty(WpStubs::$filters[$hook]);
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return false;
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite(): bool
    {
        return false;
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4(): string
    {
        return sprintf(
            '%04x%04x-%04x-4%03x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff),
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }
}

if (!function_exists('homlity_plugin_get_option')) {
    /**
     * @param mixed $default
     * @return mixed
     */
    function homlity_plugin_get_option(string $optionName, string $legacyOptionName, $default = false)
    {
        $value = get_option($optionName, null);
        if ($value !== null && $value !== false) {
            return $value;
        }

        $legacyValue = get_option($legacyOptionName, null);
        if ($legacyValue !== null && $legacyValue !== false) {
            return $legacyValue;
        }

        return $default;
    }
}

if (!function_exists('homlity_plugin_update_option')) {
    /** @param mixed $value */
    function homlity_plugin_update_option(string $optionName, string $legacyOptionName, $value): bool
    {
        $updated = update_option($optionName, $value);
        update_option($legacyOptionName, $value);

        return $updated;
    }
}

if (!function_exists('homlity_plugin_apply_filters')) {
    /** @return mixed */
    function homlity_plugin_apply_filters(string $tag, ?string $legacyTag, ...$args)
    {
        $value = apply_filters_ref_array($tag, $args);
        if (!$legacyTag || $legacyTag === $tag) {
            return $value;
        }

        $args[0] = $value;

        return apply_filters_ref_array($legacyTag, $args);
    }
}

if (!function_exists('absint')) {
    /** @param mixed $value */
    function absint($value): int
    {
        return abs((int) $value);
    }
}

if (!function_exists('wp_rand')) {
    function wp_rand(int $min = 0, int $max = 0): int
    {
        return $min;
    }
}

if (!function_exists('wp_get_environment_type')) {
    function wp_get_environment_type(): string
    {
        return 'production';
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo(string $key = 'name'): string
    {
        return $key === 'version' ? '6.8' : '';
    }
}

if (!function_exists('wp_next_scheduled')) {
    /** @return int|false */
    function wp_next_scheduled(string $hook, array $args = [])
    {
        return WpStubs::$cronEvents[$hook] ?? false;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = []): bool
    {
        WpStubs::$cronEvents[$hook] = $timestamp;

        return true;
    }
}

if (!function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event(int $timestamp, string $hook, array $args = []): bool
    {
        WpStubs::$cronEvents[$hook] = $timestamp;

        return true;
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook(string $hook, array $args = []): int
    {
        $cleared = isset(WpStubs::$cronEvents[$hook]) ? 1 : 0;
        unset(WpStubs::$cronEvents[$hook]);

        return $cleared;
    }
}

if (!function_exists('get_transient')) {
    /** @return mixed */
    function get_transient(string $key)
    {
        return WpStubs::$options['_transient_' . $key] ?? false;
    }
}

if (!function_exists('set_transient')) {
    /** @param mixed $value */
    function set_transient(string $key, $value, int $expiration = 0): bool
    {
        WpStubs::$options['_transient_' . $key] = $value;

        return true;
    }
}

// ── Usuarios y archivos de autor ───────────────────────────────────────────

if (!class_exists('WP_User')) {
    class WP_User
    {
        public int $ID = 0;
        public string $user_nicename = '';
        public string $display_name = '';
        public string $user_email = '';
        public string $user_url = '';

        /** @var string[] */
        public array $roles = [];

        /** @param array<string,mixed> $data */
        public function __construct(array $data = [])
        {
            $this->ID = (int) ($data['ID'] ?? 0);
            $this->user_nicename = (string) ($data['user_nicename'] ?? '');
            $this->display_name = (string) ($data['display_name'] ?? '');
            $this->user_email = (string) ($data['user_email'] ?? '');
            $this->user_url = (string) ($data['user_url'] ?? '');
            $this->roles = array_map('strval', (array) ($data['roles'] ?? []));
        }
    }
}

if (!class_exists('HomlityTestRedirect')) {
    /**
     * wp_safe_redirect() va seguido de exit() en producción, que no se puede
     * ejecutar dentro de una prueba. El stub lanza esta excepción para que la
     * prueba compruebe el destino y el código sin matar el proceso.
     */
    class HomlityTestRedirect extends \RuntimeException
    {
        public function __construct(public readonly string $location, public readonly int $status)
        {
            parent::__construct(sprintf('Redirect %d → %s', $status, $location));
        }
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata(int $userId)
    {
        return WpStubs::$users[$userId] ?? false;
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by(string $field, $value)
    {
        if ($field === 'id' || $field === 'ID') {
            return WpStubs::$users[(int) $value] ?? false;
        }

        foreach (WpStubs::$users as $user) {
            if ($field === 'slug' && $user->user_nicename === (string) $value) {
                return $user;
            }
            if ($field === 'email' && $user->user_email === (string) $value) {
                return $user;
            }
        }

        return false;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta(int $userId, string $key = '', bool $single = false)
    {
        $meta = WpStubs::$userMeta[$userId] ?? [];
        if ($key === '') {
            return $meta;
        }

        $value = $meta[$key] ?? '';

        return $single ? $value : [$value];
    }
}

if (!function_exists('is_author')) {
    function is_author(): bool
    {
        return WpStubs::$isAuthor;
    }
}

if (!function_exists('get_author_posts_url')) {
    function get_author_posts_url(int $userId, string $nicename = ''): string
    {
        if ($nicename === '') {
            $user = WpStubs::$users[$userId] ?? null;
            $nicename = $user instanceof \WP_User ? $user->user_nicename : '';
        }

        return $nicename === '' ? '' : home_url('/author/' . $nicename . '/');
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit(string $value): string
    {
        return rtrim($value, '/\\') . '/';
    }
}

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect(string $location, int $status = 302, string $by = ''): void
    {
        WpStubs::$redirects[] = ['location' => $location, 'status' => $status];

        throw new \HomlityTestRedirect($location, $status);
    }
}

if (!function_exists('get_avatar')) {
    function get_avatar($userId, int $size = 96, string $default = '', string $alt = ''): string
    {
        return sprintf(
            '<img class="avatar" src="https://gravatar.test/%d" alt="%s" width="%d" height="%d">',
            (int) $userId,
            $alt,
            $size,
            $size
        );
    }
}

if (!function_exists('wp_get_attachment_image_url')) {
    function wp_get_attachment_image_url(int $attachmentId, $size = 'thumbnail')
    {
        return $attachmentId > 0 ? 'https://example.test/uploads/' . $attachmentId . '.jpg' : false;
    }
}

if (!function_exists('wp_get_attachment_image')) {
    function wp_get_attachment_image(int $attachmentId, $size = 'thumbnail', bool $icon = false, array $attr = []): string
    {
        if ($attachmentId <= 0) {
            return '';
        }

        return sprintf(
            '<img src="https://example.test/uploads/%d.jpg" alt="%s">',
            $attachmentId,
            (string) ($attr['alt'] ?? '')
        );
    }
}

if (!function_exists('is_singular')) {
    /** @param string|string[] $types */
    function is_singular($types = ''): bool
    {
        $current = WpStubs::$singularPostType;
        if ($current === '') {
            return false;
        }
        $types = (array) $types;

        return $types === [] || $types === [''] || in_array($current, $types, true);
    }
}

if (!function_exists('is_post_type_archive')) {
    /** @param string|string[] $types */
    function is_post_type_archive($types = ''): bool
    {
        $current = WpStubs::$postTypeArchive;
        if ($current === '') {
            return false;
        }
        $types = (array) $types;

        return $types === [] || $types === [''] || in_array($current, $types, true);
    }
}

if (!function_exists('is_page')) {
    /** @param int|string $page */
    function is_page($page = ''): bool
    {
        if (WpStubs::$currentPageId <= 0) {
            return false;
        }

        return $page === '' || (int) $page === WpStubs::$currentPageId;
    }
}

if (!function_exists('is_tax')) {
    /** @param string|string[] $taxonomies */
    function is_tax($taxonomies = ''): bool
    {
        $current = WpStubs::$currentTaxonomy;
        if ($current === '') {
            return false;
        }
        $taxonomies = (array) $taxonomies;

        return $taxonomies === [] || $taxonomies === [''] || in_array($current, $taxonomies, true);
    }
}

if (!function_exists('get_the_ID')) {
    /** @return int|false */
    function get_the_ID()
    {
        return WpStubs::$currentPostId > 0 ? WpStubs::$currentPostId : false;
    }
}

if (!function_exists('get_avatar_url')) {
    /** @return string|false */
    function get_avatar_url($userId, array $args = [])
    {
        return 'https://gravatar.test/' . (int) $userId . '?s=' . (int) ($args['size'] ?? 96);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string
    {
        return trim(preg_replace('/[^a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~@\-]/', '', $email) ?? '');
    }
}

if (!function_exists('is_email')) {
    /** @return string|false */
    function is_email(string $email)
    {
        return preg_match('/^[^@\s]+@[^@\s.]+\.[^@\s]+$/', $email) === 1 ? $email : false;
    }
}

if (!function_exists('get_post_field')) {
    /** @return string */
    function get_post_field(string $field, int $postId = 0): string
    {
        if ($field === 'post_content') {
            return WpStubs::$postContent[$postId] ?? '';
        }
        if ($field === 'post_title') {
            return WpStubs::$postTitles[$postId] ?? '';
        }

        return '';
    }
}

if (!function_exists('get_the_terms')) {
    /** @return array<int,object>|false */
    function get_the_terms($post, string $taxonomy)
    {
        $postId = is_object($post) ? (int) $post->ID : (int) $post;
        $terms = WpStubs::$postTerms[$postId][$taxonomy] ?? [];

        return $terms === [] ? false : $terms;
    }
}

if (!function_exists('get_the_date')) {
    function get_the_date(string $format = '', int $postId = 0): string
    {
        return date($format !== '' ? $format : 'Y-m-d', 1700000000);
    }
}

if (!function_exists('get_the_modified_date')) {
    function get_the_modified_date(string $format = '', int $postId = 0): string
    {
        return date($format !== '' ? $format : 'Y-m-d', 1710000000);
    }
}

if (!function_exists('get_the_post_thumbnail_url')) {
    /** @return string|false */
    function get_the_post_thumbnail_url(int $postId = 0, $size = 'post-thumbnail')
    {
        return WpStubs::$thumbnails[$postId] ?? false;
    }
}

if (!function_exists('get_site_icon_url')) {
    function get_site_icon_url(int $size = 512): string
    {
        return (string) (WpStubs::$options['site_icon_url'] ?? '');
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n(string $format, $timestamp = false): string
    {
        return date($format, $timestamp === false ? 1710000000 : (int) $timestamp);
    }
}

if (!function_exists('current_time')) {
    /** @return string|int */
    function current_time(string $type = 'mysql', $gmt = 0)
    {
        return $type === 'timestamp' ? 1710000000 : date('Y-m-d H:i:s', 1710000000);
    }
}

if (!function_exists('wp_get_referer')) {
    /** @return string|false */
    function wp_get_referer()
    {
        return false;
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post(string $html): string
    {
        return $html;
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e(string $text, string $domain = ''): void
    {
        echo esc_html($text);
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e(string $text, string $domain = ''): void
    {
        echo esc_attr($text);
    }
}

if (!function_exists('sanitize_hex_color')) {
    /** @return string|null */
    function sanitize_hex_color(string $color): ?string
    {
        return preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color) === 1 ? $color : null;
    }
}
