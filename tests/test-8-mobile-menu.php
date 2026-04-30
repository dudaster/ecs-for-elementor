<?php
/**
 * Test 8 — Mobile Menu: funcționalitate
 *
 * Verifică:
 *  - Hook nav-menu/section_layout/before_section_end înregistrat
 *  - Controalele upgrade_nav_menu_controls() sunt responsive
 *  - upgrade_layout() înlocuiește controlul layout cu responsiv
 *  - Controale responsive disponibile pe nav-menu widget
 *  - Asset CSS există
 */

$manager = ECS_Core::instance()->modules();

ecs_section( 'mobile_menu — hook nav-menu controls' );

if ( $manager->is_active( 'mobile_menu' ) ) {

	// Verificăm hook-ul principal
	global $wp_filter;
	$hook  = 'elementor/element/nav-menu/section_layout/before_section_end';
	$found = false;
	if ( ! empty( $wp_filter[ $hook ] ) ) {
		foreach ( $wp_filter[ $hook ]->callbacks as $prio => $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$fn = $cb['function'];
				if ( is_array( $fn ) && is_object( $fn[0] ) && $fn[0] instanceof ECS_Mobile_Menu_Module ) {
					$found = true;
				}
			}
		}
	}
	ecs_ok( $found, 'Hook nav-menu/section_layout/before_section_end → ECS_Mobile_Menu_Module callback' );

	// Verificăm că metoda upgrade_nav_menu_controls există
	$module = $manager->get( 'mobile_menu' );
	ecs_ok( method_exists( $module, 'upgrade_nav_menu_controls' ), 'Metoda upgrade_nav_menu_controls() există' );

} else {
	ecs_ok( true, 'mobile_menu inactiv — skip' );
}

// ── Test: widget nav-menu are controale responsive ────────────────────────────

ecs_section( 'mobile_menu — controale responsive pe nav-menu widget' );

if ( $manager->is_active( 'mobile_menu' ) ) {

	$widgets_manager = \Elementor\Plugin::$instance->widgets_manager;
	$nav_widget = $widgets_manager->get_widget_types( 'nav-menu' );

	if ( $nav_widget ) {
		$controls = $nav_widget->get_controls();

		// ECS înlocuiește controlul 'layout' cu un ADD_RESPONSIVE_CONTROL
		// Controalele responsive Elementor au sufixe _tablet și _mobile
		$has_layout_tablet = isset( $controls['layout_tablet'] );
		$has_layout_mobile = isset( $controls['layout_mobile'] );

		ecs_ok(
			$has_layout_tablet || $has_layout_mobile,
			'Control layout responsive (layout_tablet sau layout_mobile) prezent pe nav-menu'
		);

		// ecs_force_breakpoint confirmat prezent
		ecs_ok( isset( $controls['ecs_force_breakpoint'] ), 'Control ecs_force_breakpoint adăugat' );

		// Verificăm tipul controlului layout (Elementor strippează 'options' din get_controls())
		$layout_ctrl = $controls['layout'] ?? null;
		ecs_ok( $layout_ctrl !== null, 'Control layout există pe nav-menu' );
		ecs_ok( ( $layout_ctrl['type'] ?? '' ) === 'select', 'Control layout este de tip select' );

		// Verificăm că opțiunile sunt definite în sursa PHP
		$module_src = file_get_contents( ECS_PATH . 'modules/mobile-menu/class-ecs-mobile-menu-module.php' );
		ecs_ok( strpos( $module_src, "'horizontal'" ) !== false, 'Opțiunea horizontal definită în sursă' );
		ecs_ok( strpos( $module_src, "'vertical'" ) !== false,   'Opțiunea vertical definită în sursă' );
		ecs_ok( strpos( $module_src, "'dropdown'" ) !== false,   'Opțiunea dropdown definită în sursă' );

		// Verificăm selectors CSS pentru layout (CSS custom props)
		if ( $layout_ctrl ) {
			$selectors_dict = $layout_ctrl['selectors_dictionary'] ?? [];
			ecs_ok(
				strpos( $selectors_dict['dropdown'] ?? '', '--ecs-nav-toggle-display:flex' ) !== false,
				"layout dropdown selector setează --ecs-nav-toggle-display:flex"
			);
		}

	} else {
		ecs_ok( true, 'Widget nav-menu nu este disponibil (Elementor Pro necesar) — skip' );
	}

} else {
	ecs_ok( true, 'mobile_menu inactiv — skip' );
}

// ── Test: asset CSS ───────────────────────────────────────────────────────────

ecs_section( 'mobile_menu — asset CSS' );

if ( $manager->is_active( 'mobile_menu' ) ) {

	$css_file = ECS_PATH . 'modules/mobile-menu/assets/css/ecs-mobile-menu.css';
	ecs_ok( file_exists( $css_file ), 'ecs-mobile-menu.css există' );

	if ( file_exists( $css_file ) ) {
		$css = file_get_contents( $css_file );
		ecs_ok( ! empty( $css ),                                           'CSS mobile-menu nu este gol' );
		ecs_ok( strpos( $css, '--ecs-nav-' ) !== false,                    'CSS conține variabile --ecs-nav-*' );
		ecs_ok( strpos( $css, 'ecs-nav-layout-dropdown' ) !== false,       'CSS are stiluri pentru ecs-nav-layout-dropdown' );
	}

	$js_file = ECS_PATH . 'modules/mobile-menu/assets/js/ecs-mobile-menu-editor.js';
	ecs_ok( file_exists( $js_file ), 'ecs-mobile-menu-editor.js există' );

} else {
	ecs_ok( true, 'mobile_menu inactiv — skip' );
}
