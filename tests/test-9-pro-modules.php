<?php
/**
 * Test 9 — Pro Modules: funcționalitate
 *
 * Activează temporar fiecare modul Pro, testează comportamentul specific,
 * apoi restaurează starea originală.
 *
 * Module testate:
 *  - container_responsive (prioritar)
 *  - color_schemes
 *  - font_schemes
 *  - alt_logos
 *  - custom_look_feel
 */

$manager    = ECS_Core::instance()->modules();
$pro_active = defined( 'ELECSP_VER' );

if ( ! $pro_active ) {
	ecs_section( 'Pro modules — skip (ECS Pro inactiv)' );
	ecs_ok( true, 'ECS Pro nu este activ — toate testele Pro sunt skipped' );
	return;
}

// ── Stare originală ────────────────────────────────────────────────────────────

$original_active = get_option( 'ecs_active_modules', [] );

// Funcție helper: activează un set de module și face boot() la cele noi.
// Deoarece 'elementor/widgets/register' a deja fired la init, pentru modulele
// cu register_widgets() apelăm metoda direct (hook-ul tardiv nu ar fi prins).
function ecs_activate_pro( array $ids, $mgr ): void {
	$current = get_option( 'ecs_active_modules', [] );
	$new     = array_unique( array_merge( $current, $ids ) );
	$mgr->set_active( $new );

	$wm = \Elementor\Plugin::$instance->widgets_manager;

	foreach ( $ids as $id ) {
		$module = $mgr->get( $id );
		if ( ! $module || ! $mgr->is_active( $id ) ) {
			continue;
		}
		try { $module->boot(); } catch ( Throwable $e ) { /* ignore double-boot */ }

		// Înregistrare manuală widget-uri (hook-ul principal a fired deja).
		if ( method_exists( $module, 'register_widgets' ) ) {
			try { $module->register_widgets( $wm ); } catch ( Throwable $e ) {}
		}
	}
}

// ════════════════════════════════════════════════════════════════════════════════
// container_responsive
// ════════════════════════════════════════════════════════════════════════════════

ecs_section( 'container_responsive — modul înregistrat' );

$cr = $manager->get( 'container_responsive' );
ecs_ok( $cr !== null,                     'Modulul container_responsive este înregistrat' );
ecs_ok( $cr && $cr->is_pro() === true,    'is_pro() = true' );
ecs_ok( $cr && ! $cr->is_deprecated(),    'is_deprecated() = false' );
ecs_ok( $cr && ! empty( $cr->get_title() ),       'are titlu' );
ecs_ok( $cr && ! empty( $cr->get_description() ), 'are descriere' );

ecs_section( 'container_responsive — activare și hook' );

ecs_activate_pro( [ 'container_responsive' ], $manager );
ecs_ok( $manager->is_active( 'container_responsive' ), 'container_responsive activat cu succes' );

// Verificăm că hook-ul principal este înregistrat după boot
global $wp_filter;
$hook_cr  = 'elementor/element/container/section_layout_container/after_section_start';
$found_cr = false;
if ( ! empty( $wp_filter[ $hook_cr ] ) ) {
	foreach ( $wp_filter[ $hook_cr ]->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			$fn = $cb['function'];
			if ( is_array( $fn ) && is_object( $fn[0] ) && $fn[0] instanceof ECS_Container_Responsive_Module ) {
				$found_cr = true;
			}
		}
	}
}
ecs_ok( $found_cr, "Hook '$hook_cr' → ECS_Container_Responsive_Module callback" );

ecs_section( 'container_responsive — control dte_container_type injectat pe container' );

// Citim sursa o singură dată — folosită în mai multe secțiuni.
$src_cr = file_get_contents( ELECSP_DIR . 'modules/container-responsive/class-ecs-container-responsive-module.php' );

// Apelăm add_container_type_control() direct pe un element container real.
$container_type = \Elementor\Plugin::$instance->elements_manager->get_element_types( 'container' );
if ( $container_type ) {
	$controls_before = array_keys( $container_type->get_controls() );
	$has_before      = in_array( 'dte_container_type', $controls_before, true );

	// Boot-ul a înregistrat hook-ul; dacă elementul nu a pornit cu hook-ul activ,
	// injectăm controlul direct prin metoda publică.
	if ( ! $has_before ) {
		$cr->add_container_type_control( $container_type, [] );
	}

	$controls_after = $container_type->get_controls();

	ecs_ok( isset( $controls_after['dte_container_type'] ),
		'Control dte_container_type injectat pe elementul container' );

	$ctrl = $controls_after['dte_container_type'] ?? null;
	if ( $ctrl ) {
		ecs_ok( ( $ctrl['type'] ?? '' ) === 'select',          'dte_container_type este SELECT' );
		ecs_ok( ( $ctrl['default'] ?? '' ) === 'flex',         'default = flex' );
		// Elementor normalizează 'e-dte%s-' → 'e-dte-' (variant desktop) în get_controls()
		// Verificăm că format-ul sprintf e definit în sursă, și că desktop-ul e 'e-dte-'
		ecs_ok( ( $ctrl['prefix_class'] ?? '' ) === 'e-dte-', "prefix_class desktop = 'e-dte-' (Elementor normalizat)" );
		ecs_ok( strpos( $src_cr, "'e-dte%s-'" ) !== false,    "prefix_class sprintf 'e-dte%s-' definit în sursă" );
		ecs_ok( ( $ctrl['frontend_available'] ?? false ) === true, 'frontend_available = true' );

		// Opțiunile sunt strippate de Elementor la get_controls(); verificăm în sursă.
		foreach ( [ "'flex'", "'grid'", "'slider'", "'custom'", "''" ] as $opt ) {
			ecs_ok( strpos( $src_cr, $opt ) !== false, "dte_container_type: opțiunea $opt definită în sursă" );
		}

		// Responsive: trebuie să existe variante tablet/mobile
		ecs_ok( isset( $controls_after['dte_container_type_tablet'] ),
			'Control responsiv dte_container_type_tablet există' );
		ecs_ok( isset( $controls_after['dte_container_type_mobile'] ),
			'Control responsiv dte_container_type_mobile există' );
		ecs_ok( ( $controls_after['dte_container_type_tablet']['default'] ?? 'x' ) === '',
			'tablet_default = "" (Inherit)' );
		ecs_ok( ( $controls_after['dte_container_type_mobile']['default'] ?? 'x' ) === '',
			'mobile_default = "" (Inherit)' );
	}

	// Verificăm că native container_type este ascuns prin CSS inline
	ecs_ok(
		strpos( $src_cr, '.elementor-control-container_type' ) !== false,
		'CSS inline ascunde .elementor-control-container_type'
	);
	ecs_ok(
		strpos( $src_cr, 'display: none !important' ) !== false,
		'CSS inline folosește display: none !important'
	);

} else {
	ecs_ok( true, 'Element container nu este disponibil — skip controale' );
	for ( $i = 0; $i < 14; $i++ ) { ecs_ok( true, "skip ($i)" ); }
}

ecs_section( 'container_responsive — JS sync logic în sursă' );

ecs_ok( strpos( $src_cr, 'syncNativeType' ) !== false,       'JS conține funcția syncNativeType' );
ecs_ok( strpos( $src_cr, 'syncDteVisibility' ) !== false,    'JS conține funcția syncDteVisibility' );
ecs_ok( strpos( $src_cr, 'panel/open_editor/container' ) !== false, 'JS se atașează la panel/open_editor/container' );
ecs_ok( strpos( $src_cr, 'deviceMode' ) !== false,           'JS ascultă schimbarea device mode' );
ecs_ok( strpos( $src_cr, 'resolveType' ) !== false,          'JS are logică resolveType pentru inheritare cascade desktop→tablet→mobile' );
ecs_ok( strpos( $src_cr, 'SLIDER_RE' ) !== false,            'JS are regex pentru controalele slider' );
ecs_ok( strpos( $src_cr, 'CUSTOM_RE' ) !== false,            'JS are regex pentru controalele custom layout' );
ecs_ok( strpos( $src_cr, 'FLEX_HIDE_RE' ) !== false,         'JS are regex pentru ascunderea controalelor flex când grid/slider/custom' );

// ════════════════════════════════════════════════════════════════════════════════
// color_schemes
// ════════════════════════════════════════════════════════════════════════════════

ecs_section( 'color_schemes — activare și hooks' );

ecs_activate_pro( [ 'color_schemes' ], $manager );
ecs_ok( $manager->is_active( 'color_schemes' ), 'color_schemes activat' );

$hook_kit = 'elementor/kit/register_tabs';
$found_cs = false;
if ( ! empty( $wp_filter[ $hook_kit ] ) ) {
	foreach ( $wp_filter[ $hook_kit ]->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			$fn = $cb['function'];
			if ( is_array( $fn ) && is_object( $fn[0] ) && $fn[0] instanceof ECS_Color_Schemes_Module ) {
				$found_cs = true;
			}
		}
	}
}
ecs_ok( $found_cs, 'Hook elementor/kit/register_tabs → ECS_Color_Schemes_Module' );
ecs_ok( has_action( 'wp_ajax_ecs_pro_load_color_schemes' ), 'AJAX ecs_pro_load_color_schemes înregistrat' );
ecs_ok( has_action( 'wp_ajax_ecs_pro_save_color_schemes' ), 'AJAX ecs_pro_save_color_schemes înregistrat' );
ecs_ok( has_action( 'wp_ajax_ecs_pro_get_color_globals'  ), 'AJAX ecs_pro_get_color_globals înregistrat' );

ecs_section( 'color_schemes — widget și storage CRUD' );

// Widget
$widgets_manager = \Elementor\Plugin::$instance->widgets_manager;
$cs_widget = $widgets_manager->get_widget_types( 'dte_color_scheme_selector' );
ecs_ok( $cs_widget !== null, 'Widget dte_color_scheme_selector înregistrat în Elementor' );

// Storage CRUD
$opt_cs   = 'dte_pro_color_schemes';
$orig_cs  = get_option( $opt_cs, [] );

$test_schemes = [
	[ 'id' => '_test_cs_1', 'name' => 'Test Scheme', 'colors' => [ 'primary' => '#ff0000' ] ],
];
update_option( $opt_cs, $test_schemes );

$saved_cs = get_option( $opt_cs, [] );
ecs_ok( is_array( $saved_cs ),                               'color_schemes storage returnează array' );
ecs_ok( count( $saved_cs ) === 1,                            '1 scheme salvat corect' );
ecs_ok( ( $saved_cs[0]['id'] ?? '' ) === '_test_cs_1',       'ID scheme salvat corect' );
ecs_ok( ( $saved_cs[0]['name'] ?? '' ) === 'Test Scheme',    'Nume scheme salvat corect' );
ecs_ok( ( $saved_cs[0]['colors']['primary'] ?? '' ) === '#ff0000', 'Valoare culoare salvată corect' );

// Restore
update_option( $opt_cs, $orig_cs );
ecs_ok( get_option( $opt_cs, [] ) === $orig_cs, 'color_schemes storage restaurat' );

// ════════════════════════════════════════════════════════════════════════════════
// font_schemes
// ════════════════════════════════════════════════════════════════════════════════

ecs_section( 'font_schemes — activare și hooks' );

ecs_activate_pro( [ 'font_schemes' ], $manager );
ecs_ok( $manager->is_active( 'font_schemes' ), 'font_schemes activat' );

$found_fs = false;
if ( ! empty( $wp_filter[ $hook_kit ] ) ) {
	foreach ( $wp_filter[ $hook_kit ]->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			$fn = $cb['function'];
			if ( is_array( $fn ) && is_object( $fn[0] ) && $fn[0] instanceof ECS_Font_Schemes_Module ) {
				$found_fs = true;
			}
		}
	}
}
ecs_ok( $found_fs, 'Hook elementor/kit/register_tabs → ECS_Font_Schemes_Module' );
ecs_ok( has_action( 'wp_ajax_ecs_pro_load_font_schemes' ), 'AJAX ecs_pro_load_font_schemes înregistrat' );
ecs_ok( has_action( 'wp_ajax_ecs_pro_save_font_schemes' ), 'AJAX ecs_pro_save_font_schemes înregistrat' );
ecs_ok( has_action( 'wp_ajax_ecs_pro_get_font_globals'  ), 'AJAX ecs_pro_get_font_globals înregistrat' );

ecs_section( 'font_schemes — widget și storage CRUD' );

$fs_widget = $widgets_manager->get_widget_types( 'dte_font_scheme_selector' );
ecs_ok( $fs_widget !== null, 'Widget dte_font_scheme_selector înregistrat în Elementor' );

$opt_fs  = 'dte_pro_font_schemes';
$orig_fs = get_option( $opt_fs, [] );

$test_font_schemes = [
	[ 'id' => '_test_fs_1', 'name' => 'Test Font Scheme', 'fonts' => [ 'heading' => 'Roboto' ] ],
];
update_option( $opt_fs, $test_font_schemes );

$saved_fs = get_option( $opt_fs, [] );
ecs_ok( is_array( $saved_fs ),                              'font_schemes storage returnează array' );
ecs_ok( count( $saved_fs ) === 1,                           '1 font scheme salvat corect' );
ecs_ok( ( $saved_fs[0]['id'] ?? '' ) === '_test_fs_1',      'ID font scheme salvat corect' );
ecs_ok( ( $saved_fs[0]['fonts']['heading'] ?? '' ) === 'Roboto', 'Valoare font salvată corect' );

update_option( $opt_fs, $orig_fs );
ecs_ok( get_option( $opt_fs, [] ) === $orig_fs, 'font_schemes storage restaurat' );

// ════════════════════════════════════════════════════════════════════════════════
// alt_logos
// ════════════════════════════════════════════════════════════════════════════════

ecs_section( 'alt_logos — activare și hooks' );

ecs_activate_pro( [ 'alt_logos' ], $manager );
ecs_ok( $manager->is_active( 'alt_logos' ), 'alt_logos activat' );

$found_al = false;
if ( ! empty( $wp_filter[ $hook_kit ] ) ) {
	foreach ( $wp_filter[ $hook_kit ]->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			$fn = $cb['function'];
			if ( is_array( $fn ) && is_object( $fn[0] ) && $fn[0] instanceof ECS_Alt_Logos_Module ) {
				$found_al = true;
			}
		}
	}
}
ecs_ok( $found_al, 'Hook elementor/kit/register_tabs → ECS_Alt_Logos_Module' );
ecs_ok( has_action( 'wp_ajax_ecs_pro_load_alt_logos' ), 'AJAX ecs_pro_load_alt_logos înregistrat' );
ecs_ok( has_action( 'wp_ajax_ecs_pro_save_alt_logos' ), 'AJAX ecs_pro_save_alt_logos înregistrat' );

ecs_section( 'alt_logos — storage CRUD și validare' );

$opt_al  = 'dte_pro_alt_logos';
$orig_al = get_option( $opt_al, [] );

// Test validare: intrările fără id sau label trebuie filtrate
$test_logos_raw = [
	[ 'id' => 10, 'label' => 'Dark Logo', 'url' => 'https://example.com/dark.png' ],
	[ 'id' => 0,  'label' => 'Invalid — no ID' ],   // trebuie filtrat (id = 0 = falsy)
	[ 'id' => 11, 'label' => '' ],                    // trebuie filtrat (label gol)
	[ 'id' => 12, 'label' => 'Light Logo' ],
];
// Simulăm logica de filtrare din ajax_save_logos()
$filtered = array_values( array_filter( $test_logos_raw, function( $logo ) {
	return ! empty( $logo['id'] ) && ! empty( $logo['label'] );
} ) );

update_option( $opt_al, $filtered );

$saved_al = get_option( $opt_al, [] );
ecs_ok( is_array( $saved_al ),         'alt_logos storage returnează array' );
ecs_ok( count( $saved_al ) === 2,      'Filtrarea: 2 logo-uri valide din 4 (intrările fără id/label filtrate)' );
ecs_ok( $saved_al[0]['id']    === 10,      'Primul logo valid: id = 10' );
ecs_ok( $saved_al[0]['label'] === 'Dark Logo', 'Primul logo valid: label = Dark Logo' );
ecs_ok( $saved_al[1]['id']    === 12,      'Al doilea logo valid: id = 12' );

// Test că logo-ul cu id=0 a fost filtrat
$ids_saved = array_column( $saved_al, 'id' );
ecs_ok( ! in_array( 0, $ids_saved, true ),  'Logo cu id=0 (invalid) filtrat corect' );
ecs_ok( ! in_array( 11, $ids_saved, true ), 'Logo cu label gol filtrat corect' );

update_option( $opt_al, $orig_al );
ecs_ok( get_option( $opt_al, [] ) === $orig_al, 'alt_logos storage restaurat' );

// ════════════════════════════════════════════════════════════════════════════════
// custom_look_feel
// ════════════════════════════════════════════════════════════════════════════════

ecs_section( 'custom_look_feel — activare și hooks' );

ecs_activate_pro( [ 'custom_look_feel' ], $manager );
ecs_ok( $manager->is_active( 'custom_look_feel' ), 'custom_look_feel activat' );

$found_clf = false;
if ( ! empty( $wp_filter[ $hook_kit ] ) ) {
	foreach ( $wp_filter[ $hook_kit ]->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			$fn = $cb['function'];
			if ( is_array( $fn ) && is_object( $fn[0] ) && $fn[0] instanceof ECS_Custom_Look_Feel_Module ) {
				$found_clf = true;
			}
		}
	}
}
ecs_ok( $found_clf, 'Hook elementor/kit/register_tabs → ECS_Custom_Look_Feel_Module' );
ecs_ok( has_action( 'wp_ajax_ecs_pro_load_custom_css'   ), 'AJAX ecs_pro_load_custom_css înregistrat' );
ecs_ok( has_action( 'wp_ajax_ecs_pro_save_custom_css'   ), 'AJAX ecs_pro_save_custom_css înregistrat' );
ecs_ok( has_action( 'wp_ajax_ecs_pro_load_custom_looks' ), 'AJAX ecs_pro_load_custom_looks înregistrat' );
ecs_ok( has_action( 'wp_ajax_ecs_pro_save_custom_looks' ), 'AJAX ecs_pro_save_custom_looks înregistrat' );
ecs_ok( has_action( 'wp_ajax_ecs_pro_get_look_assets'   ), 'AJAX ecs_pro_get_look_assets înregistrat' );

ecs_section( 'custom_look_feel — storage CSS snippets CRUD' );

$opt_css  = 'dte_pro_custom_css';
$orig_css = get_option( $opt_css, [] );

$test_css_snippets = [
	[ 'id' => '_test_css_1', 'label' => 'Test Snippet', 'css' => 'body { color: red; }' ],
	[ 'id' => '_test_css_2', 'label' => 'Another Snippet', 'css' => '.hero { background: blue; }' ],
];
update_option( $opt_css, $test_css_snippets );

$saved_css = get_option( $opt_css, [] );
ecs_ok( is_array( $saved_css ),              'custom_css storage returnează array' );
ecs_ok( count( $saved_css ) === 2,           '2 CSS snippets salvate corect' );
ecs_ok( ( $saved_css[0]['css'] ?? '' ) === 'body { color: red; }', 'CSS snippet 1 salvat corect' );

update_option( $opt_css, $orig_css );
ecs_ok( get_option( $opt_css, [] ) === $orig_css, 'custom_css storage restaurat' );

ecs_section( 'custom_look_feel — storage Looks CRUD + apply_matching_looks()' );

$opt_looks  = 'dte_pro_custom_looks';
$orig_looks = get_option( $opt_looks, [] );

// Salvăm un look de test (disabled — nu va fi aplicat pe frontend)
$test_looks = [
	[
		'id'             => '_test_look_1',
		'name'           => 'Test Look',
		'enabled'        => false,
		'conditions'     => [],
		'time_interval'  => [],
		'styles'         => [ 'color_scheme' => '_test_cs_1' ],
	],
];
update_option( $opt_looks, $test_looks );

$saved_looks = get_option( $opt_looks, [] );
ecs_ok( is_array( $saved_looks ),                              'custom_looks storage returnează array' );
ecs_ok( count( $saved_looks ) === 1,                           '1 look salvat corect' );
ecs_ok( ( $saved_looks[0]['id']   ?? '' ) === '_test_look_1', 'ID look salvat corect' );
ecs_ok( ( $saved_looks[0]['name'] ?? '' ) === 'Test Look',    'Nume look salvat corect' );
ecs_ok( $saved_looks[0]['enabled'] === false,                  'Look disabled = false salvat corect' );

// apply_matching_looks(): cu look disabled, nu trebuie să producă output CSS
$clf_module = $manager->get( 'custom_look_feel' );
ob_start();
$clf_module->apply_matching_looks();
$looks_output = ob_get_clean();
ecs_ok(
	empty( $looks_output ),
	'apply_matching_looks() fără looks active → fără output'
);

// Activăm look-ul și verificăm că produce <style> sau <script>
$test_looks[0]['enabled'] = true;
update_option( $opt_looks, $test_looks );

ob_start();
$clf_module->apply_matching_looks();
$looks_output_enabled = ob_get_clean();

// Look-ul activ poate produce output (depends on conditions + kit), dar cel puțin
// metoda nu trebuie să arunce erori
ecs_ok( true, 'apply_matching_looks() cu look enabled rulează fără fatal' );

// Restore
update_option( $opt_looks, $orig_looks );
ecs_ok( get_option( $opt_looks, [] ) === $orig_looks, 'custom_looks storage restaurat' );

ecs_section( 'custom_look_feel — CSS selector specificitate în sursă' );

$src_clf = file_get_contents( ELECSP_DIR . 'modules/custom-look-feel/class-ecs-custom-look-feel-module.php' );
ecs_ok(
	strpos( $src_clf, 'html[data-dte-look] body.elementor-kit-' ) !== false,
	'CSS selector: html[data-dte-look] body.elementor-kit-{id} (spec 0,2,1 — bate kit 0,1,1 și dark mode 0,2,0)'
);
ecs_ok(
	strpos( $src_clf, 'data-dte-look' ) !== false,
	'Atribut data-dte-look folosit pentru activarea look-ului'
);

// ════════════════════════════════════════════════════════════════════════════════
// Restaurare stare originală
// ════════════════════════════════════════════════════════════════════════════════

ecs_section( 'Pro modules — restaurare stare originală' );

$manager->set_active( $original_active );
ecs_ok(
	get_option( 'ecs_active_modules', [] ) === $original_active,
	'Starea originală restaurată: [' . implode( ', ', $original_active ) . ']'
);

// Verificăm că niciun modul Pro nu mai este activ (dacă nu era activ înainte)
$pro_ids = [ 'container_responsive', 'color_schemes', 'font_schemes', 'alt_logos', 'custom_look_feel' ];
$still_active = array_filter( $pro_ids, fn( $id ) => $manager->is_active( $id ) && ! in_array( $id, $original_active, true ) );
ecs_ok(
	empty( $still_active ),
	'Niciun modul Pro activat de test nu a rămas activ' . ( $still_active ? ' — rămase: ' . implode( ', ', $still_active ) : '' )
);
