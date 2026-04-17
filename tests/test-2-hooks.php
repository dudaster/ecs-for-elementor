<?php
/**
 * Test 2 — Boot & Hooks
 * Verifică că fiecare modul activ și-a înregistrat hook-urile WordPress așteptate.
 */

$manager    = ECS_Core::instance()->modules();
$pro_active = defined( 'ELECSP_VER' );

// Helper: verifică dacă un hook/filter are callback înregistrat care conține $keyword.
function ecs_has_hook( string $hook, string $keyword, int $priority = -1 ): bool {
	global $wp_filter;
	if ( empty( $wp_filter[ $hook ] ) ) return false;
	foreach ( $wp_filter[ $hook ]->callbacks as $prio => $callbacks ) {
		if ( $priority >= 0 && $prio !== $priority ) continue;
		foreach ( $callbacks as $cb ) {
			$fn = $cb['function'];
			$name = is_array( $fn )
				? ( is_object( $fn[0] ) ? get_class( $fn[0] ) : $fn[0] ) . '::' . $fn[1]
				: ( is_string( $fn ) ? $fn : '' );
			if ( stripos( $name, $keyword ) !== false ) return true;
		}
	}
	return false;
}

// ── Color Scheme ──────────────────────────────────────────────────────────────

ecs_section( 'color_scheme — hooks' );
if ( $manager->is_active( 'color_scheme' ) ) {
	ecs_ok( ecs_has_hook( 'language_attributes', 'Color_Scheme' ), 'language_attributes → inject data-dte-scheme' );
	ecs_ok( ecs_has_hook( 'wp_head', 'Color_Scheme' ),             'wp_head → dark mode CSS output' );
	ecs_ok( ecs_has_hook( 'elementor/kit/register_tabs', 'Color_Scheme' ), 'elementor/kit/register_tabs → Default Colours tab' );
	ecs_ok( ecs_has_hook( 'elementor/widgets/register', 'Color_Scheme' ),  'elementor/widgets/register → Dark Mode Switcher widget' );
} else {
	ecs_ok( true, 'color_scheme inactiv — skip' );
}

// ── Container Layout ──────────────────────────────────────────────────────────

ecs_section( 'container_layout — hooks' );
if ( $manager->is_active( 'container_layout' ) ) {
	ecs_ok( ecs_has_hook( 'elementor/documents/register', 'Container_Layout' ), 'elementor/documents/register → Custom Layout document' );
	ecs_ok(
		has_action( 'wp_ajax_ecs_preview_layout' ),
		'wp_ajax_ecs_preview_layout → AJAX handler înregistrat'
	);
	ecs_ok( ecs_has_hook( 'elementor/element/container/section_layout_container/after_section_start', 'Container_Layout' ), 'container controls hook' );
} else {
	ecs_ok( true, 'container_layout inactiv — skip' );
}

// ── Mobile Menu ───────────────────────────────────────────────────────────────

ecs_section( 'mobile_menu — hooks' );
if ( $manager->is_active( 'mobile_menu' ) ) {
	ecs_ok(
		ecs_has_hook( 'elementor/element/nav-menu/section_layout/before_section_end', 'Mobile_Menu' ),
		'nav-menu controls hook → responsive controls injectate'
	);
} else {
	ecs_ok( true, 'mobile_menu inactiv — skip' );
}

// ── Editorial Text ────────────────────────────────────────────────────────────

ecs_section( 'editorial_text — hooks' );
if ( $manager->is_active( 'editorial_text' ) ) {
	ecs_ok(
		ecs_has_hook( 'elementor/widgets/register', 'Editorial_Text' ),
		'elementor/widgets/register → ECS Editorial Text widget'
	);
} else {
	ecs_ok( true, 'editorial_text inactiv — skip' );
}

// ── Style Templates ───────────────────────────────────────────────────────────

ecs_section( 'style_templates — hooks' );
if ( $manager->is_active( 'style_templates' ) ) {
	ecs_ok( ecs_has_hook( 'elementor/element/before_section_start', 'Style_Templates' ), 'before_section_start → injectare secțiune Style Templates' );
	ecs_ok( ecs_has_hook( 'elementor/frontend/widget/before_render', 'Style_Templates' ), 'before_render → merge preset styles' );
	ecs_ok( has_action( 'wp_ajax_ecs_style_templates_list' ),      'AJAX ecs_style_templates_list înregistrat' );
	ecs_ok( has_action( 'wp_ajax_ecs_style_templates_save_new' ),  'AJAX ecs_style_templates_save_new înregistrat' );
	ecs_ok( has_action( 'wp_ajax_ecs_style_templates_overwrite' ), 'AJAX ecs_style_templates_overwrite înregistrat' );
	ecs_ok( has_action( 'wp_ajax_ecs_style_templates_get' ),       'AJAX ecs_style_templates_get înregistrat' );
	ecs_ok( has_action( 'wp_ajax_ecs_style_templates_delete' ),    'AJAX ecs_style_templates_delete înregistrat' );
} else {
	ecs_ok( true, 'style_templates inactiv — skip' );
}

// ── Pro: Color Schemes ────────────────────────────────────────────────────────

ecs_section( 'color_schemes (PRO) — hooks' );
if ( $pro_active && $manager->is_active( 'color_schemes' ) ) {
	ecs_ok( ecs_has_hook( 'elementor/kit/register_tabs', 'Color_Schemes' ), 'kit/register_tabs → Color Schemes tab' );
	ecs_ok( ecs_has_hook( 'elementor/widgets/register', 'Color_Schemes' ),  'widgets/register → Color Scheme widget' );
	ecs_ok( has_action( 'wp_ajax_ecs_color_schemes_load' ), 'AJAX ecs_color_schemes_load înregistrat' );
} elseif ( ! $pro_active ) {
	ecs_ok( true, 'ECS Pro inactiv — skip' );
} else {
	ecs_ok( true, 'color_schemes inactiv — skip' );
}

// ── Pro: Font Schemes ─────────────────────────────────────────────────────────

ecs_section( 'font_schemes (PRO) — hooks' );
if ( $pro_active && $manager->is_active( 'font_schemes' ) ) {
	ecs_ok( ecs_has_hook( 'elementor/kit/register_tabs', 'Font_Schemes' ), 'kit/register_tabs → Font Schemes tab' );
	ecs_ok( ecs_has_hook( 'elementor/widgets/register', 'Font_Schemes' ),  'widgets/register → Font Scheme widget' );
	ecs_ok( has_action( 'wp_ajax_ecs_font_schemes_load' ), 'AJAX ecs_font_schemes_load înregistrat' );
} elseif ( ! $pro_active ) {
	ecs_ok( true, 'ECS Pro inactiv — skip' );
} else {
	ecs_ok( true, 'font_schemes inactiv — skip' );
}

// ── Pro: Alt Logos ────────────────────────────────────────────────────────────

ecs_section( 'alt_logos (PRO) — hooks' );
if ( $pro_active && $manager->is_active( 'alt_logos' ) ) {
	ecs_ok( ecs_has_hook( 'elementor/kit/register_tabs', 'Alt_Logos' ), 'kit/register_tabs → Alt Logos tab' );
	ecs_ok( has_action( 'wp_ajax_ecs_alt_logos_load' ), 'AJAX ecs_alt_logos_load înregistrat' );
	ecs_ok( has_action( 'wp_ajax_ecs_alt_logos_save' ), 'AJAX ecs_alt_logos_save înregistrat' );
} elseif ( ! $pro_active ) {
	ecs_ok( true, 'ECS Pro inactiv — skip' );
} else {
	ecs_ok( true, 'alt_logos inactiv — skip' );
}

// ── Pro: Custom Look & Feel ───────────────────────────────────────────────────

ecs_section( 'custom_look_feel (PRO) — hooks' );
if ( $pro_active && $manager->is_active( 'custom_look_feel' ) ) {
	ecs_ok( ecs_has_hook( 'elementor/kit/register_tabs', 'Custom_Look' ), 'kit/register_tabs → Custom Looks tab' );
	ecs_ok( ecs_has_hook( 'wp_head', 'Custom_Look' ),                     'wp_head → aplică look-ul activ pe frontend' );
	ecs_ok( has_action( 'wp_ajax_ecs_custom_looks_load' ), 'AJAX ecs_custom_looks_load înregistrat' );
	ecs_ok( has_action( 'wp_ajax_ecs_custom_looks_save' ), 'AJAX ecs_custom_looks_save înregistrat' );
} elseif ( ! $pro_active ) {
	ecs_ok( true, 'ECS Pro inactiv — skip' );
} else {
	ecs_ok( true, 'custom_look_feel inactiv — skip' );
}

// ── Pro: Container Responsive ─────────────────────────────────────────────────

ecs_section( 'container_responsive (PRO) — hooks' );
if ( $pro_active && $manager->is_active( 'container_responsive' ) ) {
	ecs_ok(
		ecs_has_hook( 'elementor/element/container/section_layout_container/after_section_start', 'Container_Responsive' ),
		'container/section_layout_container → responsive dte_container_type control'
	);
} elseif ( ! $pro_active ) {
	ecs_ok( true, 'ECS Pro inactiv — skip' );
} else {
	ecs_ok( true, 'container_responsive inactiv — skip' );
}
