<?php
/**
 * ECS Admin
 *
 * Registers the "ECS → Modules" page in WP Admin and handles
 * saving the module toggle settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ECS_Admin {

	private ECS_Modules_Manager $manager;

	public function __construct( ECS_Modules_Manager $manager ) {
		$this->manager = $manager;

		add_action( 'admin_menu',                [ $this, 'register_menu' ] );
		add_action( 'admin_post_ecs_save_modules', [ $this, 'handle_save' ] );
		add_action( 'admin_enqueue_scripts',     [ $this, 'enqueue_assets' ] );
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'ECS - Ele Custom Skin', 'ele-custom-skin' ),
			'ECS',
			'manage_options',
			'ecs-modules',
			[ $this, 'render_modules_page' ],
			'dashicons-layout',
			58
		);

		add_submenu_page(
			'ecs-modules',
			__( 'Modules', 'ele-custom-skin' ),
			__( 'Modules', 'ele-custom-skin' ),
			'manage_options',
			'ecs-modules',
			[ $this, 'render_modules_page' ]
		);

		add_submenu_page(
			'ecs-modules',
			__( 'License', 'ele-custom-skin' ),
			__( 'License', 'ele-custom-skin' ),
			'manage_options',
			'ecs-license',
			[ $this, 'render_license_page' ]
		);
	}

	public function render_modules_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'ele-custom-skin' ) );
		}
		require_once ECS_PATH . 'admin/views/modules-page.php';
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'ele-custom-skin' ) );
		}

		check_admin_referer( 'ecs_save_modules' );

		$posted = isset( $_POST['ecs_modules'] ) ? (array) $_POST['ecs_modules'] : [];
		$ids    = array_map( 'sanitize_key', $posted );

		$this->manager->set_active( $ids );

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'ecs-modules', 'updated' => '1' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function render_license_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'ele-custom-skin' ) );
		}
		require_once ECS_PATH . 'admin/views/license-page.php';
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'toplevel_page_ecs-modules', 'ecs_page_ecs-license' ], true ) ) {
			return;
		}
		wp_enqueue_style(
			'ecs-admin',
			ECS_URL . 'assets/admin/css/ecs-admin.css',
			[],
			ECS_VERSION
		);
		wp_enqueue_script(
			'ecs-admin',
			ECS_URL . 'assets/admin/js/ecs-admin.js',
			[],
			ECS_VERSION,
			true
		);
	}
}
