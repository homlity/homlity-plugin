<?php
/**
 * Homlity Consignacion REST Controller.
 *
 * Exposes the API surface expected by the homlity-consignacion Vue web component.
 * All routes live under the "homlity" namespace so the component's urlPlugin
 * can be set to rest_url('homlity') (without trailing slash).
 *
 * Routes registered:
 *   GET  /homlity/v1/tiposInmueblePublicar
 *   GET  /homlity/v1/tiposGestion
 *   GET  /homlity/v1/data/geo/firstDivisionLevel
 *   GET  /homlity/v1/data/geo/secondDivisionLevel
 *   GET  /homlity/v1/data/geo/neighborhoods
 *   GET  /homlity/free/v1/datos/caracteristicas/tipoInmueble
 *   POST /homlity/free/v1/inmueble/crear
 *   POST /homlity/free/v1/uploads/imagen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Homlity_Consignacion_Rest_Controller {

	const NS = 'homlity';

	// ── Route registration ────────────────────────────────────────────────

	public static function register_routes(): void {
		$self = self::class;

		// Catalog endpoints
		register_rest_route( self::NS, '/v1/tiposInmueblePublicar', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $self, 'get_tipos_inmueble' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NS, '/v1/tiposGestion', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $self, 'get_tipos_gestion' ],
			'permission_callback' => '__return_true',
		] );

		// Geocoding proxy (Nominatim / OpenStreetMap) — no API key required
		register_rest_route( self::NS, '/v1/geo/search', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $self, 'geo_search' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NS, '/v1/geo/reverse', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $self, 'geo_reverse' ],
			'permission_callback' => '__return_true',
		] );

		// Geographic data endpoints
		register_rest_route( self::NS, '/v1/data/geo/firstDivisionLevel', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $self, 'get_first_division' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NS, '/v1/data/geo/secondDivisionLevel', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $self, 'get_second_division' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NS, '/v1/data/geo/neighborhoods', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $self, 'get_neighborhoods' ],
			'permission_callback' => '__return_true',
		] );

		// Features by property type
		register_rest_route( self::NS, '/free/v1/datos/caracteristicas/tipoInmueble', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $self, 'get_caracteristicas' ],
			'permission_callback' => '__return_true',
		] );

		// Property creation
		register_rest_route( self::NS, '/free/v1/inmueble/crear', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $self, 'crear_inmueble' ],
			'permission_callback' => '__return_true',
		] );

		// Image upload
		register_rest_route( self::NS, '/free/v1/uploads/imagen', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $self, 'upload_imagen' ],
			'permission_callback' => '__return_true',
		] );

		// Document (PDF) upload
		register_rest_route( self::NS, '/free/v1/uploads/documento', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $self, 'upload_documento' ],
			'permission_callback' => '__return_true',
		] );
	}

	// ── GET /v1/tiposInmueblePublicar ────────────────────────────────────

	public static function get_tipos_inmueble( WP_REST_Request $request ): WP_REST_Response {
		$terms = get_terms( [
			'taxonomy'   => 'property_type',
			'hide_empty' => false,
			'number'     => 500,
			'orderby'    => 'name',
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return new WP_REST_Response( self::default_tipos_inmueble(), 200 );
		}

		$result = [];
		foreach ( $terms as $term ) {
			$result[] = [
				'id'     => (int) $term->term_id,
				'nombre' => $term->name,
				'codigo' => (int) $term->term_id,
			];
		}

		return new WP_REST_Response( $result, 200 );
	}

	// ── GET /v1/tiposGestion ─────────────────────────────────────────────

	public static function get_tipos_gestion( WP_REST_Request $request ): WP_REST_Response {
		$terms = get_terms( [
			'taxonomy'   => 'property_operation',
			'hide_empty' => false,
			'number'     => 100,
			'orderby'    => 'name',
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return new WP_REST_Response( self::default_tipos_gestion(), 200 );
		}

		$result = [];
		foreach ( $terms as $term ) {
			$flags  = self::operation_flags( $term->name );
			$result[] = [
				'codigo'          => (int) $term->term_id,
				'nombre'          => $term->name,
				'esArriendo'      => $flags['esArriendo'],
				'esVenta'         => $flags['esVenta'],
				'esArriendoVenta' => $flags['esArriendoVenta'],
			];
		}

		return new WP_REST_Response( $result, 200 );
	}

	// ── GET /v1/data/geo/firstDivisionLevel ──────────────────────────────

	public static function get_first_division( WP_REST_Request $request ): WP_REST_Response {
		$terms = get_terms( [
			'taxonomy'   => 'property_state',
			'hide_empty' => false,
			'number'     => 1000,
			'parent'     => 0,
			'orderby'    => 'name',
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return new WP_REST_Response( [], 200 );
		}

		$result = [];
		foreach ( $terms as $term ) {
			$result[] = [
				'id'     => (int) $term->term_id,
				'nombre' => $term->name,
			];
		}

		return new WP_REST_Response( $result, 200 );
	}

	// ── GET /v1/data/geo/secondDivisionLevel ─────────────────────────────

	public static function get_second_division( WP_REST_Request $request ): WP_REST_Response {
		$parent_id = (int) $request->get_param( 'id_firstDivision' );

		if ( $parent_id <= 0 ) {
			return new WP_REST_Response( [], 400 );
		}

		// Cities are stored in property_city taxonomy, with parent = state term_id
		$terms = get_terms( [
			'taxonomy'   => 'property_city',
			'hide_empty' => false,
			'number'     => 2000,
			'parent'     => $parent_id,
			'orderby'    => 'name',
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			// Try without parent filter (some sites store cities flat)
			$terms = get_terms( [
				'taxonomy'   => 'property_city',
				'hide_empty' => false,
				'number'     => 2000,
				'parent'     => 0,
				'orderby'    => 'name',
			] );
			if ( is_wp_error( $terms ) ) {
				return new WP_REST_Response( [], 200 );
			}
		}

		$result = [];
		foreach ( (array) $terms as $term ) {
			$result[] = [
				'id'     => (int) $term->term_id,
				'nombre' => $term->name,
			];
		}

		return new WP_REST_Response( $result, 200 );
	}

	// ── GET /v1/data/geo/neighborhoods ───────────────────────────────────

	public static function get_neighborhoods( WP_REST_Request $request ): WP_REST_Response {
		$parent_id = (int) $request->get_param( 'id_secondDivision' );

		if ( $parent_id <= 0 ) {
			return new WP_REST_Response( [], 400 );
		}

		$terms = get_terms( [
			'taxonomy'   => 'property_neighborhood',
			'hide_empty' => false,
			'number'     => 3000,
			'parent'     => $parent_id,
			'orderby'    => 'name',
		] );

		if ( is_wp_error( $terms ) ) {
			return new WP_REST_Response( [], 200 );
		}

		$result = [];
		foreach ( (array) $terms as $term ) {
			$result[] = [
				'id'     => (int) $term->term_id,
				'nombre' => $term->name,
			];
		}

		return new WP_REST_Response( $result, 200 );
	}

	// ── GET /v1/geo/search  (proxy → Nominatim) ──────────────────────────

	public static function geo_search( WP_REST_Request $request ): WP_REST_Response {
		$query = sanitize_text_field( wp_unslash( $request->get_param( 'q' ) ?? '' ) );
		if ( strlen( $query ) < 3 ) {
			return new WP_REST_Response( [], 200 );
		}

		$cache_key = 'homlity_geo_' . md5( 'search_' . $query );
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) {
			return new WP_REST_Response( $cached, 200 );
		}

		$url = add_query_arg( [
			'q'              => $query,
			'format'         => 'json',
			'addressdetails' => '1',
			'limit'          => '5',
			'accept-language'=> 'es',
		], 'https://nominatim.openstreetmap.org/search' );

		$response = wp_remote_get( $url, [
			'timeout'    => 6,
			'user-agent' => 'Homlity WordPress Plugin/1.0 (homlity.com)',
		] );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new WP_REST_Response( [], 200 );
		}

		$items  = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
		$result = array_map( [ self::class, 'nominatim_to_geo' ], $items );

		set_transient( $cache_key, $result, DAY_IN_SECONDS );

		return new WP_REST_Response( $result, 200 );
	}

	// ── GET /v1/geo/reverse  (proxy → Nominatim) ─────────────────────────

	public static function geo_reverse( WP_REST_Request $request ): WP_REST_Response {
		$lat = (float) ( $request->get_param( 'lat' ) ?? 0 );
		$lon = (float) ( $request->get_param( 'lon' ) ?? 0 );

		if ( $lat === 0.0 || $lon === 0.0 ) {
			return new WP_REST_Response( [], 200 );
		}

		$cache_key = 'homlity_geo_' . md5( 'rev_' . $lat . '_' . $lon );
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) {
			return new WP_REST_Response( $cached, 200 );
		}

		$url = add_query_arg( [
			'lat'            => $lat,
			'lon'            => $lon,
			'format'         => 'json',
			'addressdetails' => '1',
			'accept-language'=> 'es',
		], 'https://nominatim.openstreetmap.org/reverse' );

		$response = wp_remote_get( $url, [
			'timeout'    => 6,
			'user-agent' => 'Homlity WordPress Plugin/1.0 (homlity.com)',
		] );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new WP_REST_Response( [], 200 );
		}

		$item   = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
		$result = ! empty( $item['lat'] ) ? [ self::nominatim_to_geo( $item ) ] : [];

		set_transient( $cache_key, $result, DAY_IN_SECONDS );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Maps a Nominatim result item to the ResultGeoDireccion format used by the Vue app.
	 */
	private static function nominatim_to_geo( array $item ): array {
		$addr    = $item['address'] ?? [];
		$bb      = $item['boundingbox'] ?? [ 0, 0, 0, 0 ];

		$city    = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? '';
		$district= $addr['suburb'] ?? $addr['neighbourhood'] ?? $addr['quarter'] ?? '';
		$street  = $addr['road'] ?? $addr['pedestrian'] ?? $addr['path'] ?? '';
		$label   = $item['display_name'] ?? '';

		return [
			'title'   => $label,
			'address' => [
				'label'    => $label,
				'city'     => $city,
				'district' => $district,
				'street'   => $street,
			],
			'position' => [
				'lat' => (float) ( $item['lat'] ?? 0 ),
				'lng' => (float) ( $item['lon'] ?? 0 ),
			],
			'mapView' => [
				'south' => (float) ( $bb[0] ?? 0 ),
				'north' => (float) ( $bb[1] ?? 0 ),
				'west'  => (float) ( $bb[2] ?? 0 ),
				'east'  => (float) ( $bb[3] ?? 0 ),
			],
		];
	}

	// ── GET /free/v1/datos/caracteristicas/tipoInmueble ──────────────────

	public static function get_caracteristicas( WP_REST_Request $request ): WP_REST_Response {
		// id_tipoinmueble is accepted but features are global — no per-type filtering in basic setup
		$terms = get_terms( [
			'taxonomy'   => 'property_feature',
			'hide_empty' => false,
			'number'     => 500,
			'orderby'    => 'parent',
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return new WP_REST_Response( [ 'data' => self::default_features() ], 200 );
		}

		// Separate parent (category) terms from child (feature) terms
		$categories    = [];
		$feature_items = [];

		foreach ( $terms as $term ) {
			if ( $term->parent === 0 ) {
				$categories[ $term->term_id ] = [
					'id'       => (int) $term->term_id,
					'name'     => $term->name,
					'features' => [],
				];
			} else {
				$feature_items[] = $term;
			}
		}

		foreach ( $feature_items as $term ) {
			$cat_id    = $term->parent;
			$value_type = get_term_meta( $term->term_id, 'value_type', true ) ?: 'boolean';
			$def_value  = get_term_meta( $term->term_id, 'def_value', true );
			if ( $def_value === '' ) {
				$def_value = null;
			}

			if ( isset( $categories[ $cat_id ] ) ) {
				$categories[ $cat_id ]['features'][] = [
					'id'        => (int) $term->term_id,
					'name'      => $term->name,
					'valueType' => $value_type,
					'defValue'  => $def_value,
				];
			}
		}

		// If no hierarchical structure, put all terms in a flat default category
		if ( empty( $categories ) ) {
			$all_features = [];
			foreach ( $terms as $term ) {
				$all_features[] = [
					'id'        => (int) $term->term_id,
					'name'      => $term->name,
					'valueType' => 'boolean',
					'defValue'  => null,
				];
			}
			$categories = [
				[
					'id'       => 1,
					'name'     => 'Características del Inmueble',
					'features' => $all_features,
				],
			];
		} else {
			$categories = array_values( $categories );
		}

		return new WP_REST_Response( [ 'data' => $categories ], 200 );
	}

	// ── POST /free/v1/inmueble/crear ─────────────────────────────────────

	public static function crear_inmueble( WP_REST_Request $request ): WP_REST_Response {
		$opts = Homlity_Consignment_Manager::options();

		// Rate limit
		if ( $opts['enable_rate_limit'] ) {
			$ip_key = 'homlity_vi_' . hash( 'sha256', sanitize_text_field( wp_unslash( (string) ( $_SERVER['REMOTE_ADDR'] ?? 'x' ) ) ) );
			$count  = (int) get_transient( $ip_key );
			$max    = apply_filters( 'homlity_consignment_rate_limit_allowed', (int) $opts['rate_limit_per_hour'] );
			if ( $count >= $max ) {
				return new WP_REST_Response( [
					'ok'      => false,
					'message' => 'Has excedido el límite de envíos por hora.',
				], 429 );
			}
		}

		$data = (array) $request->get_json_params();
		if ( empty( $data ) ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => 'Datos vacíos.' ], 400 );
		}

		// Honeypot
		if ( $opts['enable_honeypot'] && ! empty( $data['_hp'] ) ) {
			return new WP_REST_Response( [ 'ok' => true, 'message' => $opts['success_message'] ], 200 );
		}

		// Basic validation
		$errors = self::validate_inmueble( $data );
		if ( ! empty( $errors ) ) {
			return new WP_REST_Response( [ 'ok' => false, 'errors' => $errors ], 422 );
		}

		// Build payload compatible with createPropertyDirect
		$payload = self::build_payload( $data, $opts );
		$payload = apply_filters( 'homlity_consignacion_payload', $payload, $data );

		// Create the property
		$post_id = self::create_property( $payload, $opts );

		if ( $post_id <= 0 ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => $opts['error_message'] ], 500 );
		}

		// Store extra meta fields specific to the Vue format
		update_post_meta( $post_id, '_property_stratum', (int) ( $data['estrato'] ?? 0 ) );
		update_post_meta( $post_id, '_property_age', (int) ( $data['edad'] ?? 0 ) );
		update_post_meta( $post_id, '_consignment_source', 'homlity-consignacion-form' );

		/**
		 * Fires after a property is successfully created via the public consignment form.
		 *
		 * @param int   $post_id  The WordPress post ID of the created property.
		 * @param array $data     Raw InmuebleNuevo payload sent by the Vue form.
		 * @param array $payload  Normalized Homlity payload used to create the post.
		 */
		do_action( 'homlity_consignacion_property_created', $post_id, $data, $payload );

		// Rate limit increment
		if ( $opts['enable_rate_limit'] ) {
			set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );
		}

		// Logs
		if ( $opts['enable_logs'] ) {
			self::log( $post_id, $data, $opts );
		}

		// Notifications
		if ( $opts['notify_admin'] ) {
			Homlity_Consignment_Notifications::notifyAdmin( $post_id, $data, $payload );
		}

		// Generate a property code (use post_id as fallback)
		$code = get_post_meta( $post_id, '_property_code', true ) ?: (string) $post_id;

		return new WP_REST_Response( [
			'ok'      => true,
			'message' => $opts['success_message'],
			'inmueble' => [
				'codigo'  => $code,
				'post_id' => $post_id,
			],
		], 200 );
	}

	// ── POST /free/v1/uploads/imagen ─────────────────────────────────────

	public static function upload_imagen( WP_REST_Request $request ): WP_REST_Response {
		// Rate limit uploads
		$ip_key       = 'homlity_vi_img_' . hash( 'sha256', sanitize_text_field( wp_unslash( (string) ( $_SERVER['REMOTE_ADDR'] ?? 'x' ) ) ) );
		$upload_count = (int) get_transient( $ip_key );
		if ( $upload_count >= 200 ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => 'Límite de carga excedido.' ], 429 );
		}

		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => 'No se recibió ningún archivo.' ], 400 );
		}

		$result = Homlity_Consignment_Media_Handler::upload( $files['file'], 'image' );

		set_transient( $ip_key, $upload_count + 1, HOUR_IN_SECONDS );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => $result->get_error_message() ], 400 );
		}

		// The Vue component reads `data.url_full` from the upload response
		return new WP_REST_Response( [
			'ok'      => true,
			'url'     => $result['url'],
			'url_full'=> $result['url'],
			'id'      => $result['id'],
		], 200 );
	}

	// ── POST /free/v1/uploads/documento ──────────────────────────────────

	public static function upload_documento( WP_REST_Request $request ): WP_REST_Response {
		$ip_key       = 'homlity_vi_doc_' . hash( 'sha256', sanitize_text_field( wp_unslash( (string) ( $_SERVER['REMOTE_ADDR'] ?? 'x' ) ) ) );
		$upload_count = (int) get_transient( $ip_key );
		if ( $upload_count >= 50 ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => 'Límite de carga de documentos excedido.' ], 429 );
		}

		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => 'No se recibió ningún archivo.' ], 400 );
		}

		$result = Homlity_Consignment_Media_Handler::upload( $files['file'], 'brochure' );

		set_transient( $ip_key, $upload_count + 1, HOUR_IN_SECONDS );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => $result->get_error_message() ], 400 );
		}

		return new WP_REST_Response( [
			'ok'      => true,
			'url'     => $result['url'],
			'url_full'=> $result['url'],
			'nombre'  => basename( $result['url'] ),
			'id'      => $result['id'],
		], 200 );
	}

	// ── Payload builder ───────────────────────────────────────────────────

	/**
	 * Maps InmuebleNuevo (Vue format) to the existing Homlity property payload format.
	 */
	private static function build_payload( array $d, array $opts ): array {
		$propietario = (array) ( $d['propietario'] ?? [] );

		// Resolve term names from IDs
		$tipo_inmueble  = self::term_name( (int) ( $d['id_tipoinmueble'] ?? 0 ), 'property_type' );
		$tipo_gestion   = self::term_name( (int) ( $d['id_gestion'] ?? 0 ), 'property_operation' );
		$departamento   = self::term_name( (int) ( $d['id_departamento'] ?? 0 ), 'property_state' );
		$ciudad         = self::term_name( (int) ( $d['id_ciudad'] ?? 0 ), 'property_city' );

		$barrio_id   = (int) ( $d['id_barrio'] ?? 0 );
		$barrio_name = $barrio_id > 0
			? self::term_name( $barrio_id, 'property_neighborhood' )
			: sanitize_text_field( $d['barrio_nombre'] ?? '' );

		// Determine operations from gestion type
		$gestion_flags = self::operation_flags( $tipo_gestion );
		$operations    = [];
		if ( $gestion_flags['esVenta'] || $gestion_flags['esArriendoVenta'] ) {
			$operations[] = 'Venta';
		}
		if ( $gestion_flags['esArriendo'] || $gestion_flags['esArriendoVenta'] ) {
			$operations[] = 'Arriendo';
		}
		if ( empty( $operations ) && $tipo_gestion !== '' ) {
			$operations[] = sanitize_text_field( $tipo_gestion );
		}

		// Features
		$feature_names = [];
		foreach ( (array) ( $d['caracteristicas'] ?? [] ) as $c ) {
			$nombre = trim( (string) ( $c['nombre'] ?? '' ) );
			if ( $nombre !== '' ) {
				$feature_names[] = sanitize_text_field( $nombre );
			}
		}

		// Media
		$gallery      = array_values( array_filter( array_map( 'esc_url_raw', (array) ( $d['imagenes']   ?? [] ) ) ) );
		$foto_portada = esc_url_raw( $d['foto_portada'] ?? ( $gallery[0] ?? '' ) );
		$documentos   = array_values( array_filter( array_map( 'esc_url_raw', (array) ( $d['documentos'] ?? [] ) ) ) );

		// Advisor / contact
		$consignant_type = sanitize_key( $propietario['tipo'] ?? 'owner' );
		$advisor = [
			'external_id' => 'vi-' . sanitize_key( $propietario['email'] ?? (string) time() ),
			'name'        => sanitize_text_field( $propietario['nombre'] ?? '' ),
			'email'       => sanitize_email( $propietario['email'] ?? '' ),
			'phone'       => sanitize_text_field( $propietario['telefono'] ?? '' ),
			'photo'       => '',
			'role'        => self::consignant_label( $consignant_type ),
		];

		// Title (already generated by the Vue store)
		$title = sanitize_text_field( $d['nombre'] ?? '' );
		if ( $title === '' ) {
			$title = trim( implode( ' en ', array_filter( [ $tipo_inmueble, mb_strtolower( $tipo_gestion ), $ciudad ] ) ) );
		}

		$external_id = 'vi-' . time() . '-' . wp_generate_password( 8, false, false );
		$provider    = sanitize_key( apply_filters( 'homlity_consignment_default_provider', $opts['provider'] ?? 'public-consignment' ) );

		return [
			'external' => [
				'source'     => $provider,
				'id'         => $external_id,
				'updated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'raw'        => [
					'consignant_type'    => $consignant_type,
					'contact_name'       => sanitize_text_field( $propietario['nombre'] ?? '' ),
					'contact_email'      => sanitize_email( $propietario['email'] ?? '' ),
					'contact_phone'      => sanitize_text_field( $propietario['telefono'] ?? '' ),
					'identification'     => sanitize_text_field( $propietario['identificacion'] ?? '' ),
					'id_type'            => sanitize_text_field( $propietario['tipo_indentificacion'] ?? '' ),
					'stratum'            => (int) ( $d['estrato'] ?? 0 ),
					'age'                => (int) ( $d['edad'] ?? 0 ),
					'source'             => 'homlity-consignacion-form',
				],
			],
			'post' => [
				'title'             => wp_strip_all_tags( $title ),
				'description'       => wp_kses_post( $d['descripcion'] ?? '' ),
				'short_description' => '',
				'status'            => 'pending',
			],
			'location' => [
				'address'   => sanitize_text_field( $d['direccion'] ?? '' ),
				'latitude'  => (float) ( $d['latitud'] ?? 0 ),
				'longitude' => (float) ( $d['longitud'] ?? 0 ),
			],
			'pricing' => [
				'sale_price'    => (string) ( (int) ( $d['valor_venta'] ?? 0 ) ?: '' ),
				'sale_currency' => sanitize_key( $opts['default_currency'] ?? 'COP' ),
				'rent_price'    => (string) ( (int) ( $d['valor_canon'] ?? 0 ) ?: '' ),
				'rent_currency' => sanitize_key( $opts['default_currency'] ?? 'COP' ),
				'admin_price'   => (string) ( (int) ( $d['valor_admin'] ?? 0 ) ?: '' ),
				'admin_currency'=> sanitize_key( $opts['default_currency'] ?? 'COP' ),
				'admin_included'=> (bool) ( $d['valor_admin_incluida'] ?? false ),
			],
			'metrics' => [
				'area'         => (string) (float) ( $d['area_construida'] ?? 0 ),
				'area_lot'     => (string) (float) ( $d['area_lote'] ?? 0 ),
				'area_private' => '',
				'area_built'   => (string) (float) ( $d['area_construida'] ?? 0 ),
				'bedrooms'     => (int) ( $d['n_alcobas'] ?? 0 ),
				'bathrooms'    => (int) ( $d['n_banos'] ?? 0 ),
				'parking'      => (int) ( $d['n_garajes'] ?? 0 ),
				'condition'    => '',
				'year_built'   => (int) ( $d['anio_construccion'] ?? 0 ),
				'code'         => '',
				'featured'     => false,
			],
			'taxonomy' => [
				'property_operation'    => $operations,
				'property_type'         => array_filter( [ $tipo_inmueble ] ),
				'property_category'     => [],
				'property_feature'      => $feature_names,
				'property_country'      => array_filter( [ sanitize_text_field( $opts['default_country'] ?? 'Colombia' ) ] ),
				'property_state'        => array_filter( [ $departamento ] ),
				'property_city'         => array_filter( [ $ciudad ] ),
				'property_neighborhood' => array_filter( [ $barrio_name ] ),
			],
			'media' => [
				'gallery'            => $gallery,
				'featured_image_url' => $foto_portada,
				'videos'             => [],
				'tour_360'           => [],
				'photos_360'         => [],
				'brochure'           => '',
				'documentos'         => $documentos,
			],
			'advisor' => $advisor,
		];
	}

	// ── Property creation (delegates to existing service) ─────────────────

	private static function create_property( array $payload, array $opts ): int {
		$allowed_statuses = [ 'draft', 'pending' ];
		$status = in_array( $opts['default_status'], $allowed_statuses, true )
			? $opts['default_status']
			: 'pending';

		if ( apply_filters( 'homlity_consignment_allow_publish', false ) ) {
			$allowed_statuses[] = 'publish';
		}

		$payload['post']['status'] = $status;

		// Try the PSR-4 upsert service first
		$upsert_class = 'Homlity\\PluginInmobiliario\\Integrations\\CRM\\PropertyUpsertService';
		if ( class_exists( $upsert_class ) ) {
			try {
				$service = new $upsert_class();
				$result  = $service->upsert( $payload );
				if ( ! empty( $result['ok'] ) && ! empty( $result['post_id'] ) ) {
					return (int) $result['post_id'];
				}
			} catch ( \Throwable $e ) {
				// Fall through
			}
		}

		return self::create_property_direct( $payload, $status );
	}

	private static function create_property_direct( array $payload, string $status ): int {
		$post_id = wp_insert_post( [
			'post_type'    => 'property',
			'post_title'   => wp_strip_all_tags( $payload['post']['title'] ?? '' ),
			'post_content' => wp_kses_post( $payload['post']['description'] ?? '' ),
			'post_excerpt' => sanitize_text_field( $payload['post']['short_description'] ?? '' ),
			'post_status'  => $status,
		], true );

		if ( is_wp_error( $post_id ) || $post_id <= 0 ) {
			return 0;
		}

		$loc     = $payload['location'] ?? [];
		$pricing = $payload['pricing']  ?? [];
		$metrics = $payload['metrics']  ?? [];
		$advisor = $payload['advisor']  ?? [];
		$media   = $payload['media']    ?? [];
		$ext     = $payload['external'] ?? [];

		$metas = [
			'_property_address'         => sanitize_text_field( $loc['address'] ?? '' ),
			'_property_latitude'        => (string) ( $loc['latitude'] ?? '' ),
			'_property_longitude'       => (string) ( $loc['longitude'] ?? '' ),
			'_property_price_sale'      => sanitize_text_field( $pricing['sale_price'] ?? '' ),
			'_property_currency_sale'   => sanitize_key( $pricing['sale_currency'] ?? 'COP' ),
			'_property_price_rent'      => sanitize_text_field( $pricing['rent_price'] ?? '' ),
			'_property_currency_rent'   => sanitize_key( $pricing['rent_currency'] ?? 'COP' ),
			'_property_price_admin'     => sanitize_text_field( $pricing['admin_price'] ?? '' ),
			'_property_currency_admin'  => sanitize_key( $pricing['admin_currency'] ?? 'COP' ),
			'_property_admin_included'  => empty( $pricing['admin_included'] ) ? '0' : '1',
			'_property_area'            => (string) ( $metrics['area'] ?? '' ),
			'_property_area_lot'        => (string) ( $metrics['area_lot'] ?? '' ),
			'_property_area_private'    => (string) ( $metrics['area_private'] ?? '' ),
			'_property_area_built'      => (string) ( $metrics['area_built'] ?? '' ),
			'_property_bedrooms'        => (string) (int) ( $metrics['bedrooms'] ?? 0 ),
			'_property_bathrooms'       => (string) (int) ( $metrics['bathrooms'] ?? 0 ),
			'_property_parking'         => (string) (int) ( $metrics['parking'] ?? 0 ),
			'_property_age'             => (string) (int) ( $metrics['year_built'] ?? 0 ),
			'_property_featured'        => '0',
			'_property_agent_name'      => sanitize_text_field( $advisor['name'] ?? '' ),
			'_property_agent_email'     => sanitize_email( $advisor['email'] ?? '' ),
			'_property_agent_phone'     => sanitize_text_field( $advisor['phone'] ?? '' ),
			'_consignment_source'       => 'homlity-consignacion-form',
			'_consignment_external_id'  => sanitize_text_field( $ext['id'] ?? '' ),
			'_consignment_submitted_at' => current_time( 'mysql' ),
			'_property_external_source' => sanitize_key( $ext['source'] ?? '' ),
			'_property_external_id'     => sanitize_text_field( $ext['id'] ?? '' ),
		];

		foreach ( $metas as $key => $val ) {
			if ( $val !== '' ) {
				update_post_meta( $post_id, $key, $val );
			}
		}

		// Gallery + featured image
		$gallery = array_filter( array_map( 'esc_url_raw', (array) ( $media['gallery'] ?? [] ) ) );
		if ( ! empty( $gallery ) ) {
			update_post_meta( $post_id, '_property_gallery', implode( ',', $gallery ) );
			$featured_url = esc_url_raw( $media['featured_image_url'] ?? $gallery[0] );
			if ( $featured_url ) {
				update_post_meta( $post_id, '_property_featured_image_url', $featured_url );
				$att_id = attachment_url_to_postid( $featured_url );
				if ( $att_id ) {
					set_post_thumbnail( $post_id, $att_id );
				}
			}
		}

		// Documentos adjuntos (PDFs)
		$documentos = array_values( array_filter( array_map( 'esc_url_raw', (array) ( $media['documentos'] ?? [] ) ) ) );
		if ( ! empty( $documentos ) ) {
			update_post_meta( $post_id, '_property_documentos', wp_json_encode( $documentos ) );
		}

		// Taxonomies
		$flat_taxes = [
			'property_operation' => (array) ( $payload['taxonomy']['property_operation'] ?? [] ),
			'property_type'      => (array) ( $payload['taxonomy']['property_type'] ?? [] ),
			'property_feature'   => (array) ( $payload['taxonomy']['property_feature'] ?? [] ),
		];
		foreach ( $flat_taxes as $tax => $names ) {
			self::set_terms_by_name( $post_id, $tax, $names, false );
		}

		// Geographic hierarchy
		$geo = [
			'property_country'      => (array) ( $payload['taxonomy']['property_country'] ?? [] ),
			'property_state'        => (array) ( $payload['taxonomy']['property_state'] ?? [] ),
			'property_city'         => (array) ( $payload['taxonomy']['property_city'] ?? [] ),
			'property_neighborhood' => (array) ( $payload['taxonomy']['property_neighborhood'] ?? [] ),
		];
		$parent = 0;
		foreach ( $geo as $tax => $names ) {
			$name = trim( $names[0] ?? '' );
			if ( $name === '' || ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$existing = get_terms( [ 'taxonomy' => $tax, 'name' => $name, 'parent' => $parent, 'hide_empty' => false, 'number' => 1 ] );
			if ( ! empty( $existing ) && ! is_wp_error( $existing ) ) {
				$term = $existing[0];
			} else {
				$r = wp_insert_term( $name, $tax, [ 'parent' => $parent ] );
				if ( is_wp_error( $r ) ) {
					$parent = 0;
					continue;
				}
				$term = get_term( $r['term_id'], $tax );
			}
			wp_set_object_terms( $post_id, [ (int) $term->term_id ], $tax, true );
			$parent = (int) $term->term_id;
		}

		return $post_id;
	}

	// ── Validation ────────────────────────────────────────────────────────

	private static function validate_inmueble( array $d ): array {
		$errors = [];

		$propietario = (array) ( $d['propietario'] ?? [] );

		$nombre = trim( $propietario['nombre'] ?? '' );
		if ( strlen( $nombre ) < 2 ) {
			$errors['propietario.nombre'] = 'El nombre del propietario es obligatorio.';
		}

		$email = trim( $propietario['email'] ?? '' );
		if ( ! is_email( $email ) ) {
			$errors['propietario.email'] = 'El correo electrónico es obligatorio y debe ser válido.';
		}

		$telefono = trim( $propietario['telefono'] ?? '' );
		if ( $telefono === '' ) {
			$errors['propietario.telefono'] = 'El teléfono es obligatorio.';
		}

		if ( empty( $d['id_tipoinmueble'] ) ) {
			$errors['id_tipoinmueble'] = 'El tipo de inmueble es obligatorio.';
		}

		if ( empty( $d['id_gestion'] ) ) {
			$errors['id_gestion'] = 'El tipo de gestión es obligatorio.';
		}

		if ( empty( $d['id_ciudad'] ) ) {
			$errors['id_ciudad'] = 'La ciudad es obligatoria.';
		}

		return apply_filters( 'homlity_consignacion_validation_errors', $errors, $d );
	}

	// ── Helpers ───────────────────────────────────────────────────────────

	private static function term_name( int $term_id, string $taxonomy ): string {
		if ( $term_id <= 0 ) {
			return '';
		}
		$term = get_term( $term_id, $taxonomy );
		if ( is_wp_error( $term ) || empty( $term ) ) {
			return '';
		}
		return $term->name;
	}

	private static function operation_flags( string $nombre ): array {
		$lower = mb_strtolower( $nombre );
		$esArriendo      = str_contains( $lower, 'arriendo' ) || str_contains( $lower, 'alquiler' ) || str_contains( $lower, 'rent' );
		$esVenta         = str_contains( $lower, 'venta' ) || str_contains( $lower, 'sale' );
		$esArriendoVenta = $esArriendo && $esVenta;

		if ( $esArriendoVenta ) {
			$esArriendo = false;
			$esVenta    = false;
		}

		return [
			'esArriendo'      => $esArriendo,
			'esVenta'         => $esVenta,
			'esArriendoVenta' => $esArriendoVenta,
		];
	}

	private static function set_terms_by_name( int $post_id, string $taxonomy, array $names, bool $append ): void {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}
		$ids = [];
		foreach ( $names as $name ) {
			$name = trim( (string) $name );
			if ( $name === '' ) {
				continue;
			}
			$term = get_term_by( 'name', $name, $taxonomy );
			if ( ! $term ) {
				$r = wp_insert_term( $name, $taxonomy );
				if ( ! is_wp_error( $r ) ) {
					$ids[] = (int) $r['term_id'];
				}
			} else {
				$ids[] = (int) $term->term_id;
			}
		}
		if ( ! empty( $ids ) ) {
			wp_set_object_terms( $post_id, $ids, $taxonomy, $append );
		}
	}

	private static function consignant_label( string $type ): string {
		return [
			'owner'      => 'Propietario',
			'advisor'    => 'Asesor inmobiliario',
			'agency'     => 'Inmobiliaria',
			'builder'    => 'Constructor',
			'authorized' => 'Persona autorizada',
		][ $type ] ?? 'Propietario';
	}

	private static function log( int $post_id, array $data, array $opts ): void {
		$propietario = (array) ( $data['propietario'] ?? [] );
		$entry = [
			'date'    => current_time( 'mysql' ),
			'post_id' => $post_id,
			'source'  => 'homlity-consignacion-form',
			'email'   => sanitize_email( $propietario['email'] ?? '' ),
			'result'  => 'success',
			'ip_hash' => hash( 'sha256', sanitize_text_field( wp_unslash( (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) ) ) ),
		];

		$logs   = (array) get_option( 'homlity_consignment_logs', [] );
		$logs[] = $entry;
		if ( count( $logs ) > 500 ) {
			$logs = array_slice( $logs, -500 );
		}
		update_option( 'homlity_consignment_logs', $logs, false );
		update_post_meta( $post_id, '_consignment_audit', $entry );
	}

	// ── Default fallback data ─────────────────────────────────────────────

	private static function default_tipos_inmueble(): array {
		return [
			[ 'id' => 1, 'nombre' => 'Apartamento', 'codigo' => 1 ],
			[ 'id' => 2, 'nombre' => 'Casa',         'codigo' => 2 ],
			[ 'id' => 3, 'nombre' => 'Local',        'codigo' => 3 ],
			[ 'id' => 4, 'nombre' => 'Oficina',      'codigo' => 4 ],
			[ 'id' => 5, 'nombre' => 'Bodega',       'codigo' => 5 ],
			[ 'id' => 6, 'nombre' => 'Lote',         'codigo' => 6 ],
			[ 'id' => 7, 'nombre' => 'Finca',        'codigo' => 7 ],
		];
	}

	private static function default_tipos_gestion(): array {
		return [
			[ 'codigo' => 1, 'nombre' => 'Arriendo',      'esArriendo' => true,  'esVenta' => false, 'esArriendoVenta' => false ],
			[ 'codigo' => 2, 'nombre' => 'Venta',         'esArriendo' => false, 'esVenta' => true,  'esArriendoVenta' => false ],
			[ 'codigo' => 3, 'nombre' => 'Arriendo/Venta','esArriendo' => false, 'esVenta' => false, 'esArriendoVenta' => true  ],
		];
	}

	private static function default_features(): array {
		return [
			[
				'id'   => 1,
				'name' => 'Características Internas',
				'features' => [
					[ 'id' => 10, 'name' => 'Closets',          'valueType' => 'boolean', 'defValue' => null ],
					[ 'id' => 11, 'name' => 'Estudio',          'valueType' => 'boolean', 'defValue' => null ],
					[ 'id' => 12, 'name' => 'Cuarto de servicio','valueType' => 'boolean', 'defValue' => null ],
					[ 'id' => 13, 'name' => 'Cuarto útil',      'valueType' => 'boolean', 'defValue' => null ],
					[ 'id' => 14, 'name' => 'Chimenea',         'valueType' => 'boolean', 'defValue' => null ],
					[ 'id' => 15, 'name' => 'Jacuzzi',          'valueType' => 'boolean', 'defValue' => null ],
				],
			],
			[
				'id'   => 2,
				'name' => 'Características Externas',
				'features' => [
					[ 'id' => 20, 'name' => 'Piscina',          'valueType' => 'boolean', 'defValue' => null ],
					[ 'id' => 21, 'name' => 'Gimnasio',         'valueType' => 'boolean', 'defValue' => null ],
					[ 'id' => 22, 'name' => 'Salón comunal',    'valueType' => 'boolean', 'defValue' => null ],
					[ 'id' => 23, 'name' => 'Parqueadero visitantes','valueType' => 'boolean', 'defValue' => null ],
					[ 'id' => 24, 'name' => 'Portería',         'valueType' => 'boolean', 'defValue' => null ],
					[ 'id' => 25, 'name' => 'Zona BBQ',         'valueType' => 'boolean', 'defValue' => null ],
				],
			],
			[
				'id'   => 3,
				'name' => 'Zonas y servicios',
				'features' => [
					[ 'id' => 30, 'name' => 'Zona de lavandería', 'valueType' => 'boolean', 'defValue' => null ],
					[ 'id' => 31, 'name' => 'Terraza',           'valueType' => 'boolean', 'defValue' => null ],
					[ 'id' => 32, 'name' => 'Balcón',            'valueType' => 'boolean', 'defValue' => null ],
					[ 'id' => 33, 'name' => 'Jardín',            'valueType' => 'boolean', 'defValue' => null ],
					[ 'id' => 34, 'name' => 'Vista panorámica',  'valueType' => 'boolean', 'defValue' => null ],
				],
			],
		];
	}
}
