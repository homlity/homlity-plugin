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

        /** Igual que en WordPress: cuántos posts trae ESTA página. */
        public int $post_count = 0;

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
                $this->post_count = count($this->posts);
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
        /** Motor en memoria para las tablas propias del plugin. */
        public \Homlity\PluginInmobiliario\Tests\Support\FakeSqlEngine $engine;

        public int $insert_id = 0;

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

        public function __construct()
        {
            $this->engine = new \Homlity\PluginInmobiliario\Tests\Support\FakeSqlEngine();
        }

        public function get_charset_collate(): string
        {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        /** @param array<string,mixed> $data */
        public function insert(string $table, array $data, mixed $format = null): int
        {
            $result = $this->engine->insert($table, $data);
            $this->insert_id = $this->engine->insertId;

            return $result;
        }

        /**
         * @param array<string,mixed> $data
         * @param array<string,mixed> $where
         */
        public function update(string $table, array $data, array $where, mixed $format = null, mixed $whereFormat = null): int
        {
            return $this->engine->update($table, $data, $where);
        }

        /** @param array<string,mixed> $where */
        public function delete(string $table, array $where, mixed $format = null): int
        {
            return $this->engine->delete($table, $where);
        }

        /** @return list<object|array<string,mixed>> */
        public function get_results(string $query, mixed $output = null): array
        {
            $rows = $this->cannedRows($query) ?? $this->engine->select($query);

            // ARRAY_A pide filas asociativas; sin honrarlo, el código bajo
            // prueba recibiría objetos y fallaría por una razón que no existe
            // en producción.
            return $output === ARRAY_A
                ? $rows
                : array_map(static fn(array $row): object => (object) $row, $rows);
        }

        /** @return object|array<string,mixed>|null */
        public function get_row(string $query, mixed $output = null, int $y = 0)
        {
            $rows = $this->engine->select($query);
            if ($rows === []) {
                return null;
            }

            return $output === ARRAY_A ? $rows[0] : (object) $rows[0];
        }

        /** @var list<string> Sentencias pasadas a query(), que el motor no ejecuta. */
        public array $rawQueries = [];

        /**
         * Sentencias que el motor no interpreta —de momento sólo el DELETE con
         * LEFT JOIN de la purga de huérfanos—. Se registran para poder afirmar
         * sobre ellas; **no se ejecutan**, así que una prueba que dependa del
         * efecto de esta sentencia estaría comprobando nada.
         */
        public function query(string $query): int
        {
            $this->rawQueries[] = trim((string) preg_replace('/\s+/', ' ', $query));

            if (stripos(ltrim($query), 'INSERT IGNORE') === 0) {
                return $this->engine->insertIgnore($query);
            }

            return 0;
        }

        /**
         * Las filas fijadas para esta consulta, o null si no hay ninguna.
         *
         * La consulta se registra igual que en query(): una prueba que fija el
         * resultado sigue queriendo afirmar sobre el SQL que se emitió.
         *
         * @return list<array<string,mixed>>|null
         */
        private function cannedRows(string $query): ?array
        {
            foreach (WpStubs::$sqlResults as $needle => $rows) {
                if (stripos($query, (string) $needle) === false) {
                    continue;
                }

                $this->rawQueries[] = trim((string) preg_replace('/\s+/', ' ', $query));

                return $rows;
            }

            return null;
        }

        /** @return mixed */
        public function get_var(string $query, int $x = 0, int $y = 0)
        {
            // `SHOW TABLES LIKE 'x'` es la forma en que el plugin comprueba si
            // una tabla suya llegó a crearse.
            if (preg_match("/^SHOW TABLES LIKE '(.+)'$/i", trim($query), $match) === 1) {
                return in_array($match[1], WpStubs::$existingTables, true) ? $match[1] : null;
            }

            $rows = $this->engine->select($query);
            if ($rows === []) {
                return null;
            }

            return array_values($rows[0])[$x] ?? null;
        }

        /** @return list<mixed> */
        public function get_col(string $query, int $x = 0): array
        {
            return array_map(
                static fn(array $row) => array_values($row)[$x] ?? null,
                $this->engine->select($query)
            );
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

if (!function_exists('esc_attr__')) {
    function esc_attr__(string $text, string $domain = 'default'): string
    {
        return esc_attr($text);
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }
}

if (!function_exists('esc_url_raw')) {
    /**
     * Aproximación a esc_url_raw().
     *
     * WordPress **no** exige una URL absoluta: una ruta relativa como
     * `/inmueble/x/` pasa tal cual, y es justo lo que llega en `REQUEST_URI`.
     * Lo que sí filtra es el protocolo, de ahí la lista permitida.
     */
    function esc_url_raw(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (is_string($scheme) && $scheme !== '') {
            $allowed = ['http', 'https', 'mailto', 'tel', 'ftp', 'ftps', 'news', 'irc', 'webcal'];
            if (!in_array(strtolower($scheme), $allowed, true)) {
                return '';
            }
        }

        return (string) preg_replace('#[^a-zA-Z0-9\-_.~:/?\#\[\]@!$&\'()*+,;=%]#', '', $url);
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
     * Cubre dos formas: los ids de barrio ligados a una localidad por term meta
     * —lo que pide LocalityPostType::neighborhoodIds()— y la búsqueda por
     * nombre con restricción de padre que usa la homologación.
     *
     * @param array<string,mixed> $args
     * @return list<int|\WP_Term>
     */
    function get_terms(array $args = []): array
    {
        foreach ((array) ($args['meta_query'] ?? []) as $clause) {
            if (($clause['key'] ?? '') === '_parent_locality') {
                return WpStubs::$localityNeighborhoods[(int) ($clause['value'] ?? 0)] ?? [];
            }
        }

        $terms = array_values(WpStubs::$terms[$args['taxonomy'] ?? ''] ?? []);

        if (isset($args['name'])) {
            $terms = array_values(array_filter(
                $terms,
                static fn(\WP_Term $t): bool => $t->name === (string) $args['name']
            ));
        }
        if (array_key_exists('parent', $args)) {
            $terms = array_values(array_filter(
                $terms,
                static fn(\WP_Term $t): bool => $t->parent === (int) $args['parent']
            ));
        }
        if (isset($args['number']) && (int) $args['number'] > 0) {
            $terms = array_slice($terms, 0, (int) $args['number']);
        }

        // Sin `name` ni `parent` la llamada es un listado: se conserva la forma
        // antigua —sólo ids— porque es lo que esperan quienes ya la usaban.
        if (!isset($args['name']) && !array_key_exists('parent', $args)) {
            return array_map(static fn(\WP_Term $t): int => $t->term_id, $terms);
        }

        return $terms;
    }
}

if (!function_exists('taxonomy_exists')) {
    function taxonomy_exists(string $taxonomy): bool
    {
        return in_array($taxonomy, WpStubs::$registeredTaxonomies, true);
    }
}

if (!function_exists('wp_insert_term')) {
    /**
     * @param array<string,mixed> $args
     * @return array<string,int>|\WP_Error
     */
    function wp_insert_term(string $name, string $taxonomy, array $args = [])
    {
        $parent = (int) ($args['parent'] ?? 0);

        // WordPress rechaza el duplicado y devuelve el id existente dentro del
        // error: quien llama tiene que saber leerlo.
        foreach (WpStubs::$terms[$taxonomy] ?? [] as $term) {
            if ($term->name === $name && $term->parent === $parent) {
                return new \WP_Error('term_exists', 'El término ya existe', $term->term_id);
            }
        }

        $termId = ++WpStubs::$nextTermId;
        WpStubs::$terms[$taxonomy][$termId] = new \WP_Term($termId, $taxonomy, sanitize_title($name), $name, $parent);

        return ['term_id' => $termId, 'term_taxonomy_id' => $termId];
    }
}

if (!function_exists('wp_set_object_terms')) {
    /**
     * @param int|string|array<int,int|string> $terms
     * @return list<int>
     */
    function wp_set_object_terms(int $objectId, $terms, string $taxonomy, bool $append = false): array
    {
        $ids = array_map('intval', (array) $terms);
        $existing = $append ? (WpStubs::$objectTerms[$objectId][$taxonomy] ?? []) : [];
        WpStubs::$objectTerms[$objectId][$taxonomy] = array_values(array_unique(array_merge($existing, $ids)));

        return WpStubs::$objectTerms[$objectId][$taxonomy];
    }
}

if (!function_exists('update_term_meta')) {
    /** @param mixed $value */
    function update_term_meta(int $termId, string $key, $value): bool
    {
        WpStubs::$termMeta[$termId][$key] = $value;

        return true;
    }
}

if (!function_exists('wp_cache_get')) {
    /** @return mixed */
    function wp_cache_get(string $key, string $group = '', bool $force = false, mixed &$found = null)
    {
        $found = array_key_exists($group . '|' . $key, WpStubs::$cache);

        return $found ? WpStubs::$cache[$group . '|' . $key] : false;
    }
}

if (!function_exists('wp_cache_set')) {
    /** @param mixed $value */
    function wp_cache_set(string $key, $value, string $group = '', int $expire = 0): bool
    {
        WpStubs::$cache[$group . '|' . $key] = $value;

        return true;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete(string $key, string $group = ''): bool
    {
        unset(WpStubs::$cache[$group . '|' . $key]);

        return true;
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
        return WpStubs::$isAdminScreen;
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
        if ($key === 'version') {
            return '6.8';
        }

        return (string) (WpStubs::$options['blog' . $key] ?? WpStubs::$options[$key] ?? '');
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
        WpStubs::$cronRecurrences[$hook] = $recurrence;
        WpStubs::$scheduleCalls[] = ['hook' => $hook, 'recurrence' => $recurrence, 'timestamp' => $timestamp];

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

        /**
         * Baja del usuario en WordPress: 0 es alta, cualquier otra cosa no.
         * Lo escriben los plugins de gestión de usuarios, y el plugin lo mira
         * antes de enseñar a nadie.
         */
        public int $user_status = 0;

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
            $this->user_status = (int) ($data['user_status'] ?? 0);
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
            // WordPress escapa el alt aquí dentro. Sin hacerlo, este doble
            // haría fallar a quien le pasa el nombre en crudo —que es lo
            // correcto— por un agujero que en producción no existe.
            esc_attr($alt),
            $size,
            $size
        );
    }
}

if (!function_exists('wp_get_attachment_image_url')) {
    function wp_get_attachment_image_url(int $attachmentId, $size = 'thumbnail')
    {
        // Un id declarado con URL vacía representa un adjunto que ya no existe:
        // WordPress devuelve false, y quien lo llama tiene que tener un plan B.
        if (array_key_exists($attachmentId, WpStubs::$attachmentUrls)) {
            return WpStubs::$attachmentUrls[$attachmentId] !== ''
                ? WpStubs::$attachmentUrls[$attachmentId]
                : false;
        }

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
        if (WpStubs::$avatarsDisabled) {
            return false;
        }

        return 'https://gravatar.test/' . (int) $userId . '?s=' . (int) ($args['size'] ?? 96);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string
    {
        $clean = trim(preg_replace('/[^a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~@\-]/', '', $email) ?? '');

        // Igual que WordPress: lo que no llega a ser un correo se descarta
        // entero, no se guarda a medias.
        return is_email($clean) ? $clean : '';
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
        if ($field === 'post_excerpt') {
            return WpStubs::$postExcerpt[$postId] ?? '';
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
    /**
     * Aproximación a wp_kses_post(): conserva el HTML editorial y quita lo que
     * WordPress nunca deja pasar en el contenido de una entrada.
     */
    function wp_kses_post(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('#</?(script|style|iframe|object|embed)\b[^>]*>#i', '', $html) ?? '';
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';

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

if (!function_exists('locate_template')) {
    /**
     * Sin tema instalado no hay plantilla que sobrescriba a la del plugin.
     *
     * @param string|string[] $templateNames
     */
    function locate_template($templateNames, bool $load = false, bool $requireOnce = true): string
    {
        return '';
    }
}

if (!function_exists('strip_shortcodes')) {
    function strip_shortcodes(string $content): string
    {
        return (string) preg_replace('/\[\/?[a-zA-Z0-9_-]+[^\]]*\]/', '', $content);
    }
}

if (!function_exists('wpautop')) {
    /** Versión reducida: envuelve en <p> lo que no venga ya en bloques. */
    function wpautop(string $text, bool $lineBreaks = true): string
    {
        $text = trim($text);
        if ($text === '' || preg_match('/^\s*<(p|div|ul|ol|table|h[1-6])\b/i', $text) === 1) {
            return $text;
        }

        $paragraphs = preg_split('/\n\s*\n/', $text) ?: [];
        $out = '';
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph !== '') {
                $out .= '<p>' . ($lineBreaks ? nl2br($paragraph) : $paragraph) . "</p>\n";
            }
        }

        return $out;
    }
}

if (!function_exists('wp_get_attachment_image_src')) {
    /**
     * @return array{0:string,1:int,2:int,3:bool}|false
     */
    function wp_get_attachment_image_src(int $attachmentId, $size = 'thumbnail', bool $icon = false)
    {
        if ($attachmentId <= 0) {
            return false;
        }

        [$width, $height] = WpStubs::$attachmentSizes[$attachmentId] ?? [600, 400];

        return ['https://example.test/uploads/' . $attachmentId . '.jpg', $width, $height, false];
    }
}

if (!function_exists('wp_get_post_terms')) {
    /**
     * @param array<string,mixed> $args
     * @return array<int,mixed>|\WP_Error
     */
    function wp_get_post_terms(int $postId, string $taxonomy = 'post_tag', array $args = [])
    {
        if (isset(WpStubs::$postTermsError[$taxonomy])) {
            return new \WP_Error('invalid_taxonomy', WpStubs::$postTermsError[$taxonomy]);
        }

        $terms = WpStubs::$postTerms[$postId][$taxonomy] ?? [];
        $fields = (string) ($args['fields'] ?? 'all');

        if ($fields === 'ids') {
            return array_values(array_map(static fn($t): int => (int) $t->term_id, $terms));
        }
        if ($fields === 'names') {
            return array_values(array_map(static fn($t): string => (string) $t->name, $terms));
        }
        if ($fields === 'slugs') {
            return array_values(array_map(static fn($t): string => (string) $t->slug, $terms));
        }

        return array_values($terms);
    }
}

if (!function_exists('wp_list_pluck')) {
    /**
     * @param array<int,mixed> $list
     * @return array<int,mixed>
     */
    function wp_list_pluck(array $list, string $field): array
    {
        return array_map(
            static fn($item) => is_object($item) ? ($item->$field ?? null) : ($item[$field] ?? null),
            $list
        );
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $value): string
    {
        return trim(wp_strip_all_tags($value));
    }
}

if (!function_exists('current_user_can')) {
    /** @param mixed ...$args */
    function current_user_can(string $capability, ...$args): bool
    {
        return in_array($capability, WpStubs::$capabilities, true);
    }
}

if (!class_exists('HomlityTestDie')) {
    /** wp_die() corta la petición; en pruebas se convierte en excepción. */
    class HomlityTestDie extends \RuntimeException
    {
    }
}

if (!function_exists('wp_die')) {
    /** @param mixed $message */
    function wp_die($message = '', $title = '', $args = []): void
    {
        throw new \HomlityTestDie(is_string($message) ? $message : 'wp_die');
    }
}

if (!function_exists('check_admin_referer')) {
    /** @return int|false */
    function check_admin_referer(string $action = '-1', string $queryArg = '_wpnonce')
    {
        WpStubs::$checkedNonces[] = ['action' => $action, 'field' => $queryArg];
        if (!WpStubs::$nonceValid) {
            throw new \HomlityTestDie('nonce inválido');
        }

        return 1;
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return WpStubs::$homeUrl . '/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('is_front_page')) {
    function is_front_page(): bool
    {
        return WpStubs::$isFrontPage;
    }
}

if (!function_exists('is_home')) {
    function is_home(): bool
    {
        return WpStubs::$isHome;
    }
}

if (!function_exists('get_home_url')) {
    function get_home_url(?int $blogId = null, string $path = ''): string
    {
        return WpStubs::$homeUrl . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('get_users')) {
    /**
     * Sólo la forma que usa AdvisorSyncService: buscar por metadatos de usuario
     * y devolver ids.
     *
     * @param array<string,mixed> $args
     * @return list<int|\WP_User>
     */
    function get_users(array $args = []): array
    {
        $found = [];

        // `include` filtra por id y es lo que usa el plugin para traerse un
        // puñado de asesores concretos; sin honrarlo, una prueba recibiría
        // todos los usuarios del sitio y pasaría por la razón equivocada.
        $include = array_map('intval', (array) ($args['include'] ?? []));

        foreach (WpStubs::$users as $user) {
            if ($include !== [] && !in_array((int) $user->ID, $include, true)) {
                continue;
            }
            $matches = true;
            foreach ((array) ($args['meta_query'] ?? []) as $clause) {
                if (!is_array($clause) || !isset($clause['key'])) {
                    continue;
                }
                if ((string) (WpStubs::$userMeta[$user->ID][$clause['key']] ?? '') !== (string) ($clause['value'] ?? '')) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                $found[] = ($args['fields'] ?? '') === 'ids' ? $user->ID : $user;
            }
        }

        if (isset($args['number']) && (int) $args['number'] > 0) {
            $found = array_slice($found, 0, (int) $args['number']);
        }

        return $found;
    }
}

if (!function_exists('sanitize_user')) {
    function sanitize_user(string $username, bool $strict = false): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9 _.\-@]/', '', $username);
    }
}

if (!function_exists('username_exists')) {
    /** @return int|false */
    function username_exists(string $username)
    {
        foreach (WpStubs::$users as $user) {
            if ($user->user_nicename === $username) {
                return $user->ID;
            }
        }

        return false;
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password(int $length = 12, bool $specialChars = true, bool $extraSpecial = false): string
    {
        return substr(str_repeat('aA1!', (int) ceil($length / 4)), 0, $length);
    }
}

if (!function_exists('wp_insert_user')) {
    /**
     * @param array<string,mixed> $data
     * @return int|\WP_Error
     */
    function wp_insert_user(array $data)
    {
        $email = (string) ($data['user_email'] ?? '');
        if ($email === '' || !is_email($email)) {
            return new \WP_Error('invalid_email', 'Correo no válido');
        }

        $userId = ++WpStubs::$nextUserId;
        WpStubs::$users[$userId] = new \WP_User([
            'ID'            => $userId,
            'user_nicename' => (string) ($data['user_login'] ?? ''),
            'display_name'  => (string) ($data['display_name'] ?? ''),
            'user_email'    => $email,
            'roles'         => [(string) ($data['role'] ?? 'subscriber')],
        ]);

        return $userId;
    }
}

if (!function_exists('update_user_meta')) {
    /** @param mixed $value */
    function update_user_meta(int $userId, string $key, $value): bool
    {
        WpStubs::$userMeta[$userId][$key] = $value;

        return true;
    }
}

if (!class_exists('WP_Role')) {
    class WP_Role
    {
        public function __construct(public string $name = '')
        {
        }
    }
}

if (!function_exists('get_role')) {
    function get_role(string $role): ?\WP_Role
    {
        return in_array($role, WpStubs::$registeredRoles, true) ? new \WP_Role($role) : null;
    }
}

if (!function_exists('wp_insert_post')) {
    /**
     * @param array<string,mixed> $data
     * @return int|\WP_Error
     */
    function wp_insert_post(array $data, bool $wpError = false)
    {
        if (WpStubs::$postInsertError !== '') {
            $error = new \WP_Error('insert_failed', WpStubs::$postInsertError);

            return $wpError ? $error : 0;
        }

        $postId = ++WpStubs::$nextPostId;
        WpStubs::$postObjects[$postId] = new \WP_Post(array_merge(['ID' => $postId], $data));
        WpStubs::$postTitles[$postId] = (string) ($data['post_title'] ?? '');
        WpStubs::$postContent[$postId] = (string) ($data['post_content'] ?? '');
        WpStubs::$postExcerpt[$postId] = (string) ($data['post_excerpt'] ?? '');
        WpStubs::$postStatuses[$postId] = (string) ($data['post_status'] ?? 'publish');

        return $postId;
    }
}

if (!function_exists('wp_update_post')) {
    /**
     * @param array<string,mixed> $data
     * @return int|\WP_Error
     */
    function wp_update_post(array $data, bool $wpError = false)
    {
        $postId = (int) ($data['ID'] ?? 0);
        if ($postId === 0 || !isset(WpStubs::$postObjects[$postId])) {
            $error = new \WP_Error('invalid_post', 'El post no existe');

            return $wpError ? $error : 0;
        }

        foreach ($data as $key => $value) {
            if (property_exists(WpStubs::$postObjects[$postId], (string) $key)) {
                WpStubs::$postObjects[$postId]->$key = $value;
            }
        }
        WpStubs::$postTitles[$postId] = (string) ($data['post_title'] ?? WpStubs::$postTitles[$postId] ?? '');
        WpStubs::$postContent[$postId] = (string) ($data['post_content'] ?? WpStubs::$postContent[$postId] ?? '');
        WpStubs::$postExcerpt[$postId] = (string) ($data['post_excerpt'] ?? WpStubs::$postExcerpt[$postId] ?? '');
        WpStubs::$postStatuses[$postId] = (string) ($data['post_status'] ?? WpStubs::$postStatuses[$postId] ?? 'publish');

        return $postId;
    }
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax(): bool
    {
        return WpStubs::$doingAjax;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return WpStubs::$userLoggedIn;
    }
}

if (!function_exists('get_queried_object_id')) {
    function get_queried_object_id(): int
    {
        return WpStubs::$currentPostId;
    }
}

if (!function_exists('is_ssl')) {
    function is_ssl(): bool
    {
        return true;
    }
}

if (!class_exists('HomlityTestJsonResponse')) {
    /**
     * wp_send_json_*() termina la petición; en pruebas se convierte en
     * excepción para poder comprobar la respuesta sin matar el proceso.
     */
    class HomlityTestJsonResponse extends \RuntimeException
    {
        /** @param mixed $data */
        public function __construct(public readonly bool $success, public readonly mixed $data, public readonly int $status)
        {
            parent::__construct($success ? 'success' : 'error');
        }
    }
}

if (!function_exists('wp_send_json_success')) {
    /** @param mixed $data */
    function wp_send_json_success($data = null, int $statusCode = 200): void
    {
        throw new \HomlityTestJsonResponse(true, $data, $statusCode);
    }
}

if (!function_exists('wp_send_json_error')) {
    /** @param mixed $data */
    function wp_send_json_error($data = null, int $statusCode = 200): void
    {
        throw new \HomlityTestJsonResponse(false, $data, $statusCode);
    }
}

if (!function_exists('check_ajax_referer')) {
    /** @return int|false */
    function check_ajax_referer(string $action = '-1', $queryArg = false, bool $die = true)
    {
        WpStubs::$checkedNonces[] = ['action' => $action, 'field' => (string) $queryArg];
        if (!WpStubs::$nonceValid) {
            throw new \HomlityTestDie('nonce de AJAX inválido');
        }

        return 1;
    }
}

if (!function_exists('get_theme_mod')) {
    /** @param mixed $default @return mixed */
    function get_theme_mod(string $name, $default = false)
    {
        return WpStubs::$themeMods[$name] ?? $default;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $key): bool
    {
        $existed = isset(WpStubs::$options['_transient_' . $key]);
        unset(WpStubs::$options['_transient_' . $key]);

        return $existed;
    }
}

if (!function_exists('is_404')) {
    function is_404(): bool
    {
        return WpStubs::$is404;
    }
}

if (!function_exists('status_header')) {
    function status_header(int $code, string $description = ''): void
    {
        WpStubs::$statusHeaders[] = $code;
    }
}

if (!function_exists('nocache_headers')) {
    function nocache_headers(): void
    {
    }
}

if (!function_exists('get_stylesheet_directory')) {
    function get_stylesheet_directory(): string
    {
        return WpStubs::$stylesheetDirectory;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(
        string $handle,
        string $src = '',
        array $deps = [],
        $ver = false,
        $args = []
    ): void {
        WpStubs::$enqueuedScripts[$handle] = [
            'src' => $src,
            'deps' => $deps,
            'ver' => $ver,
            'args' => $args,
        ];
    }
}

if (!function_exists('checked')) {
    function checked(mixed $checked, mixed $current = true, bool $display = true): string
    {
        $html = (string) $checked === (string) $current ? " checked='checked'" : '';
        if ($display) {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        return $html;
    }
}

if (!function_exists('_n')) {
    function _n(string $single, string $plural, int $number, string $domain = 'default'): string
    {
        return $number === 1 ? $single : $plural;
    }
}

if (!function_exists('sanitize_html_class')) {
    function sanitize_html_class(string $class, string $fallback = ''): string
    {
        $sanitized = (string) preg_replace('/[^A-Za-z0-9_\- ]/', '', $class);

        return $sanitized !== '' ? $sanitized : $fallback;
    }
}
