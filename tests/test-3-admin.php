<?php
/**
 * Test 3 — Admin Page & Settings Persistence
 * Verifică admin page render, toggle modules, license state.
 */

$manager    = ECS_Core::instance()->modules();
$pro_active = defined( 'ELECSP_VER' );

// ── Admin page rendering ──────────────────────────────────────────────────────

ecs_section( 'Admin — constante și versiune' );

ecs_ok( defined( 'ECS_VERSION' ), 'ECS_VERSION definit: ' . ECS_VERSION );
ecs_ok( defined( 'ECS_PATH' ),    'ECS_PATH definit' );
ecs_ok( defined( 'ECS_URL' ),     'ECS_URL definit' );
ecs_ok( file_exists( ECS_PATH . 'admin/views/modules-page.php' ), 'modules-page.php există' );
ecs_ok( file_exists( ECS_PATH . 'admin/views/license-page.php' ), 'license-page.php există' );
ecs_ok( file_exists( ECS_PATH . 'assets/admin/css/ecs-admin.css' ), 'ecs-admin.css există' );
ecs_ok( file_exists( ECS_PATH . 'assets/admin/js/ecs-admin.js' ),  'ecs-admin.js există' );

// ── Modules page render — no PHP fatal errors ─────────────────────────────────

ecs_section( 'Admin — render modules-page.php fără erori' );

// Simulăm variabilele necesare paginii
define( 'ABSPATH', ABSPATH );

ob_start();
$error = null;
try {
	// Setăm variabilele pe care pagina le așteaptă
	set_error_handler( function( $errno, $errstr ) use ( &$error ) {
		$error = "$errno: $errstr";
		return true;
	} );
	require ECS_PATH . 'admin/views/modules-page.php';
	restore_error_handler();
} catch ( Throwable $e ) {
	$error = $e->getMessage();
}
$output = ob_get_clean();

ecs_ok( $error === null, 'modules-page.php se încarcă fără erori' . ( $error ? ": $error" : '' ) );
ecs_ok( strpos( $output, 'ecs-modules-grid' ) !== false, 'Output conține .ecs-modules-grid' );
ecs_ok( strpos( $output, 'ecs-section-header' ) !== false, 'Output conține .ecs-section-header' );
ecs_ok( strpos( $output, 'Free Modules' ) !== false, 'Output conține secțiunea Free Modules' );
ecs_ok( strpos( $output, 'Pro Modules' ) !== false,  'Output conține secțiunea Pro Modules' );
ecs_ok( strpos( $output, 'ecs-toggle' ) !== false,   'Output conține toggle-uri module' );

if ( $pro_active ) {
	ecs_ok( strpos( $output, 'ecs-pro-active-badge' ) !== false, 'Pro activ → badge Active prezent' );
} else {
	ecs_ok( strpos( $output, 'ecs-pro-cta' ) !== false, 'Pro inactiv → Get ECS Pro CTA prezent' );
}

// ── License page render ───────────────────────────────────────────────────────

ecs_section( 'Admin — render license-page.php fără erori' );

ob_start();
$error = null;
try {
	set_error_handler( function( $errno, $errstr ) use ( &$error ) {
		$error = "$errno: $errstr";
		return true;
	} );
	require ECS_PATH . 'admin/views/license-page.php';
	restore_error_handler();
} catch ( Throwable $e ) {
	$error = $e->getMessage();
}
$output_license = ob_get_clean();

ecs_ok( $error === null, 'license-page.php se încarcă fără erori' . ( $error ? ": $error" : '' ) );
ecs_ok( strpos( $output_license, 'ecs-license-card' ) !== false, 'Output conține .ecs-license-card' );

$license_key = get_option( 'elecs-license-key', '' );
if ( $pro_active && ! empty( $license_key ) ) {
	ecs_ok( strpos( $output_license, 'ecs-license-status--active' ) !== false, 'Licență activă → status active prezent' );
} elseif ( $pro_active ) {
	ecs_ok( strpos( $output_license, 'ecs-license-form' ) !== false, 'Fără licență → formular activare prezent' );
} else {
	ecs_ok( strpos( $output_license, 'ecs-pro/' ) !== false, 'Pro inactiv → link Get ECS Pro prezent' );
}

// ── Module toggle persistence ─────────────────────────────────────────────────

ecs_section( 'Settings — persistența toggle-urilor' );

$original = get_option( 'ecs_active_modules', [] );

// Activăm un set specific de module și verificăm că se salvează corect
$test_set = [ 'color_scheme', 'editorial_text', 'style_templates' ];
$manager->set_active( $test_set );

$saved = get_option( 'ecs_active_modules', [] );
ecs_ok( is_array( $saved ), 'ecs_active_modules este un array' );
ecs_ok( in_array( 'color_scheme', $saved, true ),    'color_scheme salvat ca activ' );
ecs_ok( in_array( 'editorial_text', $saved, true ),  'editorial_text salvat ca activ' );
ecs_ok( in_array( 'style_templates', $saved, true ), 'style_templates salvat ca activ' );
ecs_ok( ! in_array( 'mobile_menu', $saved, true ),   'mobile_menu salvat ca inactiv' );

ecs_ok( $manager->is_active( 'color_scheme' ),    'is_active(color_scheme) = true' );
ecs_ok( $manager->is_active( 'editorial_text' ),  'is_active(editorial_text) = true' );
ecs_ok( ! $manager->is_active( 'mobile_menu' ),   'is_active(mobile_menu) = false' );

// Restaurăm starea originală
$manager->set_active( $original );
ecs_ok(
	get_option( 'ecs_active_modules', [] ) === $original,
	'Starea originală restaurată (' . implode( ', ', $original ) . ')'
);

// ── Asset files integrity ─────────────────────────────────────────────────────

ecs_section( 'Assets — integritate fișiere' );

$assets = [
	'assets/admin/css/ecs-admin.css',
	'assets/admin/js/ecs-admin.js',
];

// Adăugăm asset-urile modulelor free active
$module_assets = [
	'color_scheme'    => [ 'modules/color-scheme/assets/js/ecs-colour-editor.js', 'modules/color-scheme/assets/css/ecs-colour-editor.css' ],
	'container_layout'=> [ 'modules/container-layout/assets/js/ecs-editor-preview.js', 'modules/container-layout/assets/css/ecs-container-layout.css' ],
];

foreach ( $assets as $rel ) {
	ecs_ok( file_exists( ECS_PATH . $rel ), "Există: $rel" );
}

foreach ( $module_assets as $module_id => $files ) {
	foreach ( $files as $rel ) {
		if ( file_exists( ECS_PATH . $rel ) ) {
			ecs_ok( true, "Există: $rel" );
		} else {
			ecs_ok( false, "LIPSĂ: $rel" );
		}
	}
}

// GIF preview assets
$previews = [ 'color_scheme', 'container_layout', 'container_responsive', 'editorial_text',
              'mobile_menu', 'style_templates', 'font_schemes', 'color_schemes', 'alt_logos', 'custom_look_feel' ];
$missing_previews = [];
foreach ( $previews as $id ) {
	if ( ! file_exists( ECS_PATH . "assets/module-previews/{$id}.gif" ) ) {
		$missing_previews[] = $id;
	}
}
ecs_ok( empty( $missing_previews ), 'Toate GIF-urile de preview există' . ( $missing_previews ? ' — lipsesc: ' . implode( ', ', $missing_previews ) : '' ) );
