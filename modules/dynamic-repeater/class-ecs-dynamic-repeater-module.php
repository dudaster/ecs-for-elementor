<?php
/**
 * Module: Dynamic Repeater Builder
 *
 * Populates any Elementor repeater from Posts, Terms, ACF Repeater, or Raw JSON.
 * Supports one-time generation in the editor and runtime binding on the frontend.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ECS_Dynamic_Repeater_Module extends ECS_Module_Base {

	const OPTION_PRESETS = 'ecs_drb_presets';
	const META_BINDINGS  = '_ecs_drb_bindings';
	const CACHE_PREFIX   = 'ecs_drb_src_';
	const CACHE_TTL      = 300;

	// ── Identity ──────────────────────────────────────────────────────────────

	public function get_id(): string {
		return 'dynamic_repeater';
	}

	public function get_title(): string {
		return __( 'Dynamic Repeater Builder', 'ele-custom-skin' );
	}

	public function get_description(): string {
		return __( 'Populate any Elementor repeater from Posts, Terms, ACF, or JSON. Supports one-time generation and runtime data binding.', 'ele-custom-skin' );
	}

	public function is_pro(): bool {
		return true;
	}

	// ── Boot ──────────────────────────────────────────────────────────────────

	public function boot(): void {
		$this->load_dependencies();

		$ajax_actions = [
			'ecs_drb_get_source_schema',
			'ecs_drb_get_available_fields',
			'ecs_drb_generate',
			'ecs_drb_get_presets',
			'ecs_drb_save_preset',
			'ecs_drb_delete_preset',
			'ecs_drb_save_binding',
			'ecs_drb_break_binding',
			'ecs_drb_get_bindings',
			'ecs_drb_get_terms',
		];

		foreach ( $ajax_actions as $action ) {
			add_action( "wp_ajax_{$action}", [ $this, 'handle_ajax' ] );
		}

		// Apply runtime bindings before a widget renders on the frontend.
		add_action( 'elementor/element/before_render', [ $this, 'apply_runtime_binding' ] );
	}

	private function load_dependencies(): void {
		$src = $this->module_path() . 'sources/';
		require_once $src . 'class-ecs-drb-source-base.php';
		require_once $src . 'class-ecs-drb-source-posts.php';
		require_once $src . 'class-ecs-drb-source-terms.php';
		require_once $src . 'class-ecs-drb-source-acf.php';
		require_once $src . 'class-ecs-drb-source-json.php';
		require_once $this->module_path() . 'class-ecs-drb-sources.php';
		require_once $this->module_path() . 'class-ecs-drb-mapper.php';

		$sources = ECS_DRB_Sources::instance();
		$sources->register( new ECS_DRB_Source_Posts() );
		$sources->register( new ECS_DRB_Source_Terms() );
		$sources->register( new ECS_DRB_Source_ACF() );
		$sources->register( new ECS_DRB_Source_JSON() );

		do_action( 'ecs_drb_register_sources', $sources );
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	public function enqueue_editor_assets(): void {
		wp_enqueue_style(
			'ecs-drb-editor',
			$this->module_url() . 'assets/css/ecs-dynamic-repeater-editor.css',
			[],
			ECS_VERSION
		);

		wp_enqueue_script(
			'ecs-drb-editor',
			$this->module_url() . 'assets/js/ecs-dynamic-repeater-editor.js',
			[ 'jquery', 'elementor-editor' ],
			ECS_VERSION,
			true
		);

		wp_localize_script( 'ecs-drb-editor', 'ecsDrb', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ecs_drb' ),
			'sources' => ECS_DRB_Sources::instance()->get_list_for_js(),
			'l10n'    => [
				'title'          => __( 'Dynamic Repeater Builder', 'ele-custom-skin' ),
				'btnLabel'       => __( 'Dynamic Populate', 'ele-custom-skin' ),
				'tabSource'      => __( 'Source', 'ele-custom-skin' ),
				'tabMapping'     => __( 'Mapping', 'ele-custom-skin' ),
				'tabOptions'     => __( 'Options', 'ele-custom-skin' ),
				'tabPresets'     => __( 'Presets', 'ele-custom-skin' ),
				'tabBinding'     => __( 'Binding', 'ele-custom-skin' ),
				'generate'       => __( 'Generate', 'ele-custom-skin' ),
				'apply'          => __( 'Apply', 'ele-custom-skin' ),
				'preview'        => __( 'Preview', 'ele-custom-skin' ),
				'loading'        => __( 'Loading…', 'ele-custom-skin' ),
				'fetchFields'    => __( 'Fetch Available Fields', 'ele-custom-skin' ),
				'noFields'       => __( 'Configure source first, then fetch available fields.', 'ele-custom-skin' ),
				'savePreset'     => __( 'Save as Preset', 'ele-custom-skin' ),
				'loadPreset'     => __( 'Load', 'ele-custom-skin' ),
				'deletePreset'   => __( 'Delete', 'ele-custom-skin' ),
				'bindToSource'   => __( 'Bind to Source', 'ele-custom-skin' ),
				'breakSync'      => __( 'Break Sync', 'ele-custom-skin' ),
				'refreshNow'     => __( 'Refresh Now', 'ele-custom-skin' ),
				'bound'          => __( 'Bound — data is generated at runtime on every page load.', 'ele-custom-skin' ),
				'notBound'       => __( 'Not bound. Apply once or bind to enable runtime generation.', 'ele-custom-skin' ),
				'autoSuggest'    => __( 'Auto-suggest Mapping', 'ele-custom-skin' ),
				'previewRows'    => __( '%d rows generated', 'ele-custom-skin' ),
				'appliedRows'    => __( '%d rows applied to repeater', 'ele-custom-skin' ),
				'cached'         => __( '(cached)', 'ele-custom-skin' ),
			],
		] );
	}

	// ── AJAX dispatcher ───────────────────────────────────────────────────────

	public function handle_ajax(): void {
		$action = preg_replace( '/^wp_ajax_(nopriv_)?ecs_drb_/', '', current_action() );
		$method = 'ajax_' . $action;
		if ( method_exists( $this, $method ) ) {
			$this->$method();
		} else {
			wp_send_json_error( [ 'message' => 'Unknown action' ] );
		}
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	private function ajax_get_source_schema(): void {
		check_ajax_referer( 'ecs_drb', 'nonce' );

		$source_id     = sanitize_key( $_POST['source_type'] ?? '' );
		$source_config = json_decode( stripslashes( $_POST['source_config'] ?? '{}' ), true ) ?: [];

		$source = ECS_DRB_Sources::instance()->get( $source_id );
		if ( ! $source ) {
			wp_send_json_error( [ 'message' => 'Unknown source type: ' . $source_id ] );
		}

		wp_send_json_success( [
			'schema' => $source->get_config_schema(),
			'fields' => $source->get_available_fields( $source_config ),
		] );
	}

	private function ajax_get_available_fields(): void {
		check_ajax_referer( 'ecs_drb', 'nonce' );

		$source_id     = sanitize_key( $_POST['source_type'] ?? '' );
		$source_config = json_decode( stripslashes( $_POST['source_config'] ?? '{}' ), true ) ?: [];

		$source = ECS_DRB_Sources::instance()->get( $source_id );
		if ( ! $source ) {
			wp_send_json_error( [ 'message' => 'Unknown source type' ] );
		}

		wp_send_json_success( [ 'fields' => $source->get_available_fields( $source_config ) ] );
	}

	private function ajax_generate(): void {
		check_ajax_referer( 'ecs_drb', 'nonce' );

		$source_id     = sanitize_key( $_POST['source_type'] ?? '' );
		$source_config = json_decode( stripslashes( $_POST['source_config'] ?? '{}' ), true ) ?: [];
		$mapping       = json_decode( stripslashes( $_POST['mapping'] ?? '[]' ), true ) ?: [];
		$field_types   = json_decode( stripslashes( $_POST['field_types'] ?? '{}' ), true ) ?: [];
		$use_cache     = filter_var( $_POST['use_cache'] ?? true, FILTER_VALIDATE_BOOLEAN );
		$cache_ttl     = intval( $_POST['cache_ttl'] ?? self::CACHE_TTL );

		$source = ECS_DRB_Sources::instance()->get( $source_id );
		if ( ! $source ) {
			wp_send_json_error( [ 'message' => 'Unknown source type' ] );
		}

		$cache_key   = self::CACHE_PREFIX . md5( $source_id . wp_json_encode( $source_config ) );
		$source_rows = false;

		if ( $use_cache ) {
			$source_rows = get_transient( $cache_key );
		}

		if ( false === $source_rows ) {
			$source_rows = $source->get_rows( $source_config );
			if ( $use_cache ) {
				set_transient( $cache_key, $source_rows, max( 60, $cache_ttl ) );
			}
		}

		$rows = ECS_DRB_Mapper::resolve( $source_rows, $mapping, $field_types );

		wp_send_json_success( [
			'rows'         => $rows,
			'source_count' => count( $source_rows ),
			'result_count' => count( $rows ),
			'cached'       => isset( $source_rows ) && get_transient( $cache_key ) !== false,
		] );
	}

	private function ajax_get_presets(): void {
		check_ajax_referer( 'ecs_drb', 'nonce' );
		wp_send_json_success( [ 'presets' => array_values( (array) get_option( self::OPTION_PRESETS, [] ) ) ] );
	}

	private function ajax_save_preset(): void {
		check_ajax_referer( 'ecs_drb', 'nonce' );

		$name   = sanitize_text_field( $_POST['name'] ?? '' );
		$config = json_decode( stripslashes( $_POST['config'] ?? '{}' ), true ) ?: [];

		if ( empty( $name ) ) {
			wp_send_json_error( [ 'message' => 'Preset name is required' ] );
		}

		$presets = (array) get_option( self::OPTION_PRESETS, [] );
		$id = 'preset_' . substr( md5( uniqid( '', true ) ), 0, 8 );
		$presets[ $id ] = [
			'id'      => $id,
			'name'    => $name,
			'config'  => $config,
			'created' => time(),
		];
		update_option( self::OPTION_PRESETS, $presets );

		wp_send_json_success( [ 'id' => $id, 'presets' => array_values( $presets ) ] );
	}

	private function ajax_delete_preset(): void {
		check_ajax_referer( 'ecs_drb', 'nonce' );

		$id = sanitize_key( $_POST['id'] ?? '' );
		$presets = (array) get_option( self::OPTION_PRESETS, [] );
		unset( $presets[ $id ] );
		update_option( self::OPTION_PRESETS, $presets );

		wp_send_json_success( [ 'presets' => array_values( $presets ) ] );
	}

	private function ajax_save_binding(): void {
		check_ajax_referer( 'ecs_drb', 'nonce' );

		$post_id     = intval( $_POST['post_id'] ?? 0 );
		$element_id  = sanitize_text_field( $_POST['element_id'] ?? '' );
		$control_key = sanitize_text_field( $_POST['control_key'] ?? '' );
		$config      = json_decode( stripslashes( $_POST['config'] ?? '{}' ), true ) ?: [];

		if ( ! $post_id || ! $element_id || ! $control_key ) {
			wp_send_json_error( [ 'message' => 'Missing required parameters' ] );
		}

		$bindings = (array) get_post_meta( $post_id, self::META_BINDINGS, true );
		$key = $element_id . ':' . $control_key;
		$bindings[ $key ] = [
			'element_id'  => $element_id,
			'control_key' => $control_key,
			'config'      => $config,
			'updated'     => time(),
		];
		update_post_meta( $post_id, self::META_BINDINGS, $bindings );

		wp_send_json_success( [ 'binding_key' => $key ] );
	}

	private function ajax_break_binding(): void {
		check_ajax_referer( 'ecs_drb', 'nonce' );

		$post_id     = intval( $_POST['post_id'] ?? 0 );
		$element_id  = sanitize_text_field( $_POST['element_id'] ?? '' );
		$control_key = sanitize_text_field( $_POST['control_key'] ?? '' );

		$bindings = (array) get_post_meta( $post_id, self::META_BINDINGS, true );
		unset( $bindings[ $element_id . ':' . $control_key ] );
		update_post_meta( $post_id, self::META_BINDINGS, $bindings );

		wp_send_json_success();
	}

	private function ajax_get_bindings(): void {
		check_ajax_referer( 'ecs_drb', 'nonce' );

		$post_id  = intval( $_POST['post_id'] ?? 0 );
		$bindings = $post_id ? (array) get_post_meta( $post_id, self::META_BINDINGS, true ) : [];

		wp_send_json_success( [ 'bindings' => array_values( $bindings ) ] );
	}

	private function ajax_get_terms(): void {
		check_ajax_referer( 'ecs_drb', 'nonce' );

		$taxonomy = sanitize_key( $_POST['taxonomy'] ?? '' );
		$search   = sanitize_text_field( $_POST['search'] ?? '' );

		if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			wp_send_json_success( [ 'terms' => [] ] );
			return;
		}

		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => 30,
			'search'     => $search,
			'fields'     => 'all',
		] );

		if ( is_wp_error( $terms ) ) {
			wp_send_json_success( [ 'terms' => [] ] );
			return;
		}

		$result = array_map( fn( $t ) => [
			'slug'  => $t->slug,
			'name'  => $t->name,
			'count' => $t->count,
		], $terms );

		wp_send_json_success( [ 'terms' => $result ] );
	}

	// ── Runtime binding ───────────────────────────────────────────────────────

	public function apply_runtime_binding( $element ): void {
		if ( ! ( $element instanceof \Elementor\Widget_Base ) ) {
			return;
		}

		// Never modify widget data in the editor — live preview should show
		// actual repeater controls so the user can still edit them.
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return;
		}

		$post_id  = get_the_ID();
		$bindings = get_post_meta( $post_id, self::META_BINDINGS, true );

		if ( ! is_array( $bindings ) || empty( $bindings ) ) {
			return;
		}

		$element_id = $element->get_id();

		foreach ( $bindings as $binding ) {
			if ( ( $binding['element_id'] ?? '' ) !== $element_id ) {
				continue;
			}

			$cfg         = $binding['config'] ?? [];
			$source_id   = sanitize_key( $cfg['source_type'] ?? '' );
			$s_config    = $cfg['source_config'] ?? [];
			$mapping     = $cfg['mapping'] ?? [];
			$field_types = $cfg['field_types'] ?? [];
			$apply_mode  = $cfg['apply_mode'] ?? 'replace';
			$use_cache   = (bool) ( $cfg['use_cache'] ?? true );
			$cache_ttl   = intval( $cfg['cache_ttl'] ?? self::CACHE_TTL );
			$control_key = $binding['control_key'] ?? '';

			if ( ! $control_key ) {
				continue;
			}

			$source = ECS_DRB_Sources::instance()->get( $source_id );
			if ( ! $source ) {
				continue;
			}

			$cache_key   = self::CACHE_PREFIX . md5( $source_id . wp_json_encode( $s_config ) );
			$source_rows = false;

			if ( $use_cache ) {
				$source_rows = get_transient( $cache_key );
			}

			if ( false === $source_rows ) {
				$source_rows = $source->get_rows( $s_config );
				if ( $use_cache ) {
					set_transient( $cache_key, $source_rows, max( 60, $cache_ttl ) );
				}
			}

			if ( empty( $source_rows ) ) {
				// Empty-state: leave existing repeater data intact.
				continue;
			}

			$new_rows = ECS_DRB_Mapper::resolve( $source_rows, $mapping, $field_types );

			if ( $apply_mode === 'append' ) {
				$existing = $element->get_settings( $control_key );
				$new_rows = array_merge( is_array( $existing ) ? $existing : [], $new_rows );
			}

			$element->set_settings( $control_key, $new_rows );
		}
	}
}
