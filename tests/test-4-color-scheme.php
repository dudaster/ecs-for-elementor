<?php
/**
 * Test 4 — Color Scheme: funcționalitate
 *
 * Verifică:
 *  - inject_scheme_html_attribute() adaugă data-dte-scheme="alt" când cookie-ul e setat
 *  - inject_dark_mode_css() produce CSS valid cu selectorul corect
 *  - Widget ECS_Color_Switcher_Widget este înregistrat în Elementor
 *  - Kitul activ are tab-ul Default Colours înregistrat
 */

$manager    = ECS_Core::instance()->modules();
$pro_active = defined( 'ELECSP_VER' );

ecs_section( 'color_scheme — inject_scheme_html_attribute()' );

if ( $manager->is_active( 'color_scheme' ) ) {

	// Ensure the module class is loaded.
	$module = $manager->get( 'color_scheme' );

	// Test 1: fără cookie → nu adaugă atribut
	unset( $_COOKIE['dte_color_scheme'] );
	$result_no_cookie = $module->inject_scheme_html_attribute( 'lang="ro-RO"' );
	ecs_ok(
		strpos( $result_no_cookie, 'data-dte-scheme' ) === false,
		'Fără cookie → data-dte-scheme absent din language_attributes'
	);

	// Test 2: cu cookie = 'alt' → adaugă atribut
	$_COOKIE['dte_color_scheme'] = 'alt';
	$result_with_cookie = $module->inject_scheme_html_attribute( 'lang="ro-RO"' );
	ecs_ok(
		strpos( $result_with_cookie, 'data-dte-scheme="alt"' ) !== false,
		'Cookie dte_color_scheme=alt → data-dte-scheme="alt" adăugat'
	);

	// Test 3: cu cookie != 'alt' → nu adaugă atribut
	$_COOKIE['dte_color_scheme'] = 'default';
	$result_other_cookie = $module->inject_scheme_html_attribute( 'lang="ro-RO"' );
	ecs_ok(
		strpos( $result_other_cookie, 'data-dte-scheme' ) === false,
		'Cookie dte_color_scheme=default → data-dte-scheme absent'
	);

	// Curățăm cookie-ul simulat
	unset( $_COOKIE['dte_color_scheme'] );

	// Test 4: atributul original este păstrat
	$base = 'lang="en-US"';
	$_COOKIE['dte_color_scheme'] = 'alt';
	$output = $module->inject_scheme_html_attribute( $base );
	ecs_ok(
		strpos( $output, $base ) !== false,
		'Atributul original lang= este păstrat în output'
	);
	unset( $_COOKIE['dte_color_scheme'] );

} else {
	ecs_ok( true, 'color_scheme inactiv — skip' );
}

// ── Dark mode CSS output ──────────────────────────────────────────────────────

ecs_section( 'color_scheme — inject_dark_mode_css()' );

if ( $manager->is_active( 'color_scheme' ) ) {
	$module = $manager->get( 'color_scheme' );

	ob_start();
	$module->inject_dark_mode_css();
	$dark_css_output = ob_get_clean();

	// Poate fi gol dacă kitul nu are culori dark configurate — asta e valid.
	// Verificăm că dacă produce ceva, structura e corectă.
	if ( ! empty( $dark_css_output ) ) {
		ecs_ok(
			strpos( $dark_css_output, '<style id="dte-dark-mode-css">' ) !== false,
			'Output conține <style id="dte-dark-mode-css">'
		);
		ecs_ok(
			preg_match( '/\[data-dte-scheme="alt"\]\s*\.elementor-kit-\d+/', $dark_css_output ) === 1,
			'Selector CSS corect: [data-dte-scheme="alt"] .elementor-kit-{id}'
		);
		ecs_ok(
			strpos( $dark_css_output, '--e-global-color-' ) !== false,
			'Output conține variabile CSS --e-global-color-*'
		);
		ecs_ok(
			strpos( $dark_css_output, '</style>' ) !== false,
			'Tag <style> este închis corect'
		);
	} else {
		ecs_ok( true, 'inject_dark_mode_css() nu produce output (kit fără culori dark) — acceptabil' );
	}

	// Test că nu aruncă erori la metodă
	$error = null;
	ob_start();
	try {
		set_error_handler( function( $errno, $errstr ) use ( &$error ) {
			if ( E_NOTICE === $errno || E_DEPRECATED === $errno ) return true;
			$error = "$errno: $errstr";
			return true;
		} );
		$module->inject_dark_mode_css();
		restore_error_handler();
	} catch ( Throwable $e ) {
		$error = $e->getMessage();
	}
	ob_get_clean();
	ecs_ok( $error === null, 'inject_dark_mode_css() nu aruncă erori fatale' . ( $error ? ": $error" : '' ) );

} else {
	ecs_ok( true, 'color_scheme inactiv — skip' );
}

// ── Anti-FOUC script ──────────────────────────────────────────────────────────

ecs_section( 'color_scheme — inject_anti_fouc_script()' );

if ( $manager->is_active( 'color_scheme' ) ) {
	$module = $manager->get( 'color_scheme' );

	ob_start();
	$module->inject_anti_fouc_script();
	$fouc_output = ob_get_clean();

	ecs_ok( strpos( $fouc_output, '<script>' ) !== false,        'Output conține <script>' );
	ecs_ok( strpos( $fouc_output, 'dte_color_scheme' ) !== false, 'Script citește cookie-ul dte_color_scheme' );
	ecs_ok( strpos( $fouc_output, 'data-dte-scheme' ) !== false, 'Script setează atribut data-dte-scheme' );
	ecs_ok( strpos( $fouc_output, 'dteSchemeConfig' ) !== false, 'Script setează window.dteSchemeConfig' );

} else {
	ecs_ok( true, 'color_scheme inactiv — skip' );
}

// ── Widget înregistrat ────────────────────────────────────────────────────────

ecs_section( 'color_scheme — widget ECS_Color_Switcher_Widget înregistrat' );

if ( $manager->is_active( 'color_scheme' ) ) {
	$widgets_manager = \Elementor\Plugin::$instance->widgets_manager;
	$widget = $widgets_manager->get_widget_types( 'dte_color_switcher' );

	ecs_ok( $widget !== null, 'Widget dte_color_switcher găsit în Elementor' );
	ecs_ok( $widget && $widget->get_name() === 'dte_color_switcher', 'get_name() = dte_color_switcher' );
	ecs_ok( $widget && ! empty( $widget->get_title() ),              'Widget are titlu' );
	ecs_ok( $widget && in_array( 'ele-custom-skin', $widget->get_categories(), true ), 'Widget în categoria ele-custom-skin' );

} else {
	ecs_ok( true, 'color_scheme inactiv — skip' );
}

// ── Kit tab Default Colours ───────────────────────────────────────────────────

ecs_section( 'color_scheme — tab Default Colours în Kit' );

if ( $manager->is_active( 'color_scheme' ) ) {
	// Verificăm că hook-ul de registrare tab este activ
	$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
	ecs_ok( $kit !== null && $kit->get_id() > 0, 'Kit activ Elementor găsit (ID: ' . ( $kit ? $kit->get_id() : 'none' ) . ')' );

	// ECS_Default_Colours_Tab trebuie să fie clasa înregistrată pentru 'global-colors'
	// Verificăm că fișierul clasei există
	$tab_file = ECS_PATH . 'modules/color-scheme/class-ecs-default-colours-tab.php';
	ecs_ok( file_exists( $tab_file ), 'class-ecs-default-colours-tab.php există' );

	// Verificăm că hook-ul elementor/kit/register_tabs este înregistrat
	global $wp_filter;
	$hook_set = ! empty( $wp_filter['elementor/kit/register_tabs'] );
	ecs_ok( $hook_set, 'Hook elementor/kit/register_tabs este înregistrat' );

} else {
	ecs_ok( true, 'color_scheme inactiv — skip' );
}
