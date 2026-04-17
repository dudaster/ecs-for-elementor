<?php
/**
 * Test 7 — Container Layout: funcționalitate
 *
 * Verifică:
 *  - Document type Custom Layout înregistrat în Elementor
 *  - AJAX handler ecs_preview_layout înregistrat
 *  - Widget placeholder înregistrat și funcțional
 *  - Clasa documentului are metodele statice necesare
 */

$manager = ECS_Core::instance()->modules();

ecs_section( 'container_layout — document type Custom Layout' );

if ( $manager->is_active( 'container_layout' ) ) {

	// Verificăm că fișierele există
	$doc_file       = ECS_PATH . 'modules/container-layout/class-ecs-custom-layout-document.php';
	$placeholder_file = ECS_PATH . 'modules/container-layout/widgets/class-ecs-container-placeholder-widget.php';

	ecs_ok( file_exists( $doc_file ),         'class-ecs-custom-layout-document.php există' );
	ecs_ok( file_exists( $placeholder_file ), 'class-ecs-container-placeholder-widget.php există' );

	// Verificăm că clasa documentului este înregistrată în Elementor
	if ( ! class_exists( 'ECS_Custom_Layout_Document', false ) ) {
		if ( file_exists( $doc_file ) ) {
			require_once $doc_file;
		}
	}

	ecs_ok( class_exists( 'ECS_Custom_Layout_Document', false ), 'Clasa ECS_Custom_Layout_Document există' );

	if ( class_exists( 'ECS_Custom_Layout_Document', false ) ) {
		// Verificăm metodele statice
		ecs_ok( method_exists( 'ECS_Custom_Layout_Document', 'get_title' ),         'get_title() există' );
		ecs_ok( method_exists( 'ECS_Custom_Layout_Document', 'get_plural_title' ),  'get_plural_title() există' );
		ecs_ok( method_exists( 'ECS_Custom_Layout_Document', 'get_properties' ),    'get_properties() există' );

		// Verificăm că titlul nu este gol
		$title = ECS_Custom_Layout_Document::get_title();
		ecs_ok( ! empty( $title ), "get_title() returnează: '$title'" );

		// Verificăm proprietățile (admin_tab_group și support_conditions)
		$props = ECS_Custom_Layout_Document::get_properties();
		ecs_ok( is_array( $props ),                             'get_properties() returnează un array' );
		ecs_ok( ( $props['admin_tab_group'] ?? '' ) === 'theme', "admin_tab_group = 'theme' (apare în Theme Builder)" );
		ecs_ok( ( $props['support_conditions'] ?? true ) === false, 'support_conditions = false' );
	}

	// Verificăm că documentul este înregistrat în Elementor
	$doc_types = \Elementor\Plugin::$instance->documents->get_document_types();
	$type_key  = 'ecs_custom_layout';
	ecs_ok(
		array_key_exists( $type_key, $doc_types ),
		"Document type '$type_key' înregistrat în Elementor (" . count( $doc_types ) . " tipuri totale)"
	);

} else {
	ecs_ok( true, 'container_layout inactiv — skip' );
}

// ── Test: AJAX handler ────────────────────────────────────────────────────────

ecs_section( 'container_layout — AJAX handler ecs_preview_layout' );

if ( $manager->is_active( 'container_layout' ) ) {

	ecs_ok( has_action( 'wp_ajax_ecs_preview_layout' ), 'wp_ajax_ecs_preview_layout înregistrat' );

	// Verificăm că acțiunea pointează spre clasa modulului
	global $wp_filter;
	$hook_found = false;
	if ( ! empty( $wp_filter['wp_ajax_ecs_preview_layout'] ) ) {
		foreach ( $wp_filter['wp_ajax_ecs_preview_layout']->callbacks as $prio => $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$fn = $cb['function'];
				if ( is_array( $fn ) && is_object( $fn[0] ) && $fn[0] instanceof ECS_Container_Layout_Module ) {
					$hook_found = true;
				}
			}
		}
	}
	ecs_ok( $hook_found, 'AJAX handler este o metodă a ECS_Container_Layout_Module' );

} else {
	ecs_ok( true, 'container_layout inactiv — skip' );
}

// ── Test: Widget placeholder ──────────────────────────────────────────────────

ecs_section( 'container_layout — widget ECS_Container_Placeholder' );

if ( $manager->is_active( 'container_layout' ) ) {

	$placeholder_file = ECS_PATH . 'modules/container-layout/widgets/class-ecs-container-placeholder-widget.php';
	if ( ! class_exists( 'ECS_Container_Placeholder_Widget', false ) ) {
		require_once $placeholder_file;
	}

	ecs_ok( class_exists( 'ECS_Container_Placeholder_Widget', false ), 'Clasa ECS_Container_Placeholder_Widget există' );

	if ( class_exists( 'ECS_Container_Placeholder_Widget', false ) ) {
		$placeholder = new ECS_Container_Placeholder_Widget( [], [] );

		ecs_ok( $placeholder->get_name() === 'dte_container_placeholder', "get_name() = 'dte_container_placeholder'" );
		ecs_ok( ! empty( $placeholder->get_title() ),                      'get_title() nu este gol' );

		// Test get_next_child() — fără copii setați returnează null
		$result = $placeholder->get_next_child();
		ecs_ok( $result === null, 'get_next_child() fără copii setați returnează null' );

		// Test set_pending_children() și get_next_child()
		$placeholder->reset_pending_children();
		ecs_ok( true, 'reset_pending_children() se apelează fără erori' );

		// Test any_consumed() — inițial false
		ecs_ok( $placeholder->any_consumed() === false, 'any_consumed() = false inițial' );

		// Verificăm că widget-ul este înregistrat în Elementor
		$widgets_manager = \Elementor\Plugin::$instance->widgets_manager;
		$registered      = $widgets_manager->get_widget_types( 'dte_container_placeholder' );
		ecs_ok( $registered !== null, 'Widget dte_container_placeholder înregistrat în Elementor' );
	}

} else {
	ecs_ok( true, 'container_layout inactiv — skip' );
}

// ── Test: hook container controls ─────────────────────────────────────────────

ecs_section( 'container_layout — hook controls container' );

if ( $manager->is_active( 'container_layout' ) ) {

	global $wp_filter;
	$hook = 'elementor/documents/register';
	$found = false;
	if ( ! empty( $wp_filter[ $hook ] ) ) {
		foreach ( $wp_filter[ $hook ]->callbacks as $prio => $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$fn = $cb['function'];
				$name = is_array( $fn )
					? ( is_object( $fn[0] ) ? get_class( $fn[0] ) : $fn[0] ) . '::' . $fn[1]
					: ( is_string( $fn ) ? $fn : '' );
				if ( stripos( $name, 'Container_Layout' ) !== false ) {
					$found = true;
				}
			}
		}
	}
	ecs_ok( $found, "Hook '$hook' conține callback din ECS_Container_Layout_Module" );

} else {
	ecs_ok( true, 'container_layout inactiv — skip' );
}

// ── Test: asset files ─────────────────────────────────────────────────────────

ecs_section( 'container_layout — asset files' );

if ( $manager->is_active( 'container_layout' ) ) {

	$assets = [
		'modules/container-layout/assets/js/ecs-editor-preview.js',
		'modules/container-layout/assets/css/ecs-container-layout.css',
	];

	foreach ( $assets as $rel ) {
		ecs_ok( file_exists( ECS_PATH . $rel ), "Există: $rel" );
	}

	// Verificăm că JS-ul conține logica de preview (AJAX call)
	$js_file = ECS_PATH . 'modules/container-layout/assets/js/ecs-editor-preview.js';
	if ( file_exists( $js_file ) ) {
		$js = file_get_contents( $js_file );
		ecs_ok( strpos( $js, 'ecs_preview_layout' ) !== false, 'JS conține referință la ecs_preview_layout AJAX action' );
		ecs_ok( strpos( $js, 'e-dte-custom' ) !== false,       'JS detectează containerele cu clasa e-dte-custom' );
	}

	// Verificăm că CSS-ul conține stiluri pentru injecție
	$css_file = ECS_PATH . 'modules/container-layout/assets/css/ecs-container-layout.css';
	if ( file_exists( $css_file ) ) {
		$css = file_get_contents( $css_file );
		ecs_ok( ! empty( $css ), 'CSS container-layout nu este gol' );
	}

} else {
	ecs_ok( true, 'container_layout inactiv — skip' );
}
