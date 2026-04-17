<?php
/**
 * Test 10 — container_responsive: toate opțiunile × toate breakpoint-urile
 *
 * Verifică:
 *  - Toate 5 opțiuni (flex/grid/slider/custom/inherit) pe desktop, tablet, mobile
 *    generează clasele CSS corecte prin prefix_class 'e-dte%s-'
 *  - Comportamentul Inherit (""): nu adaugă clasă pentru breakpoint-ul respectiv
 *  - Combinații mixte: desktop=flex/tablet=grid/mobile=inherit etc.
 *  - Settings stocate corect per breakpoint (get_settings)
 *  - Logica resolveType cascade (desktop→tablet→mobile) verificată prin PHP
 *  - Clasele e-dte-tablet-* și e-dte-mobile-* sunt distincte de desktop
 */

$manager    = ECS_Core::instance()->modules();
$pro_active = defined( 'ELECSP_VER' );

if ( ! $pro_active ) {
	ecs_section( 'container_responsive functional — skip (ECS Pro inactiv)' );
	ecs_ok( true, 'ECS Pro inactiv — skip' );
	return;
}

// ── Setup: asigurăm că controlul e adăugat pe elementul container ─────────────

$original_active = get_option( 'ecs_active_modules', [] );

$manager->set_active( array_unique( array_merge( $original_active, [ 'container_responsive' ] ) ) );
$cr_module = $manager->get( 'container_responsive' );
try { $cr_module->boot(); } catch ( Throwable $e ) {}

$container_proto = \Elementor\Plugin::$instance->elements_manager->get_element_types( 'container' );
$controls        = $container_proto->get_controls();

// Adaugă controalele dacă nu există deja (prima rulare sau modul nou activat)
if ( ! isset( $controls['dte_container_type'] ) ) {
	$cr_module->add_container_type_control( $container_proto, [] );
	$controls = $container_proto->get_controls();
}

// ── Test: controale responsive înregistrate corect ───────────────────────────

ecs_section( 'container_responsive — controale responsive și prefix_class' );

ecs_ok( isset( $controls['dte_container_type'] ),        'Control dte_container_type (desktop) înregistrat' );
ecs_ok( isset( $controls['dte_container_type_tablet'] ), 'Control dte_container_type_tablet înregistrat' );
ecs_ok( isset( $controls['dte_container_type_mobile'] ), 'Control dte_container_type_mobile înregistrat' );

ecs_ok(
	( $controls['dte_container_type']['prefix_class'] ?? '' ) === 'e-dte-',
	"prefix_class desktop = 'e-dte-'"
);
ecs_ok(
	( $controls['dte_container_type_tablet']['prefix_class'] ?? '' ) === 'e-dte-tablet-',
	"prefix_class tablet = 'e-dte-tablet-'"
);
ecs_ok(
	( $controls['dte_container_type_mobile']['prefix_class'] ?? '' ) === 'e-dte-mobile-',
	"prefix_class mobile = 'e-dte-mobile-'"
);

// ── Helper: randează container și extrage clasele e-dte-* ─────────────────────

$container_class = get_class( $container_proto );

/**
 * Randează un container cu setările date și returnează clasele e-dte-* prezente.
 */
function ecs_render_dte_classes( string $container_class, array $settings ): array {
	static $idx = 0;
	$idx++;
	$inst = new $container_class(
		[ 'id' => 'cr_test_' . $idx, 'settings' => $settings ],
		null
	);
	ob_start();
	try { $inst->print_element(); } catch ( Throwable $e ) {}
	$html = ob_get_clean();
	preg_match( '/class="([^"]+)"/', $html, $m );
	$all = explode( ' ', $m[1] ?? '' );
	return array_values( array_filter( $all, fn( $c ) => str_starts_with( $c, 'e-dte-' ) ) );
}

// ── Test: toate 5 opțiuni pe DESKTOP ─────────────────────────────────────────

ecs_section( 'container_responsive — desktop: toate 5 opțiuni' );

$desktop_cases = [
	'flex'   => 'e-dte-flex',
	'grid'   => 'e-dte-grid',
	'slider' => 'e-dte-slider',
	'custom' => 'e-dte-custom',
	''       => null,  // Inherit → fără clasă
];

foreach ( $desktop_cases as $value => $expected_class ) {
	$classes = ecs_render_dte_classes( $container_class, [ 'dte_container_type' => $value ] );
	$desktop_dte = array_values( array_filter( $classes, fn( $c ) => ! str_contains( $c, 'tablet' ) && ! str_contains( $c, 'mobile' ) ) );

	if ( $expected_class === null ) {
		ecs_ok(
			empty( $desktop_dte ),
			"desktop=''" . " (Inherit) → nicio clasă e-dte- desktop adăugată"
		);
	} else {
		ecs_ok(
			in_array( $expected_class, $desktop_dte, true ),
			"desktop='$value' → clasa '$expected_class' prezentă"
		);
	}
}

// ── Test: toate 5 opțiuni pe TABLET ──────────────────────────────────────────

ecs_section( 'container_responsive — tablet: toate 5 opțiuni' );

$tablet_cases = [
	'flex'   => 'e-dte-tablet-flex',
	'grid'   => 'e-dte-tablet-grid',
	'slider' => 'e-dte-tablet-slider',
	'custom' => 'e-dte-tablet-custom',
	''       => null,
];

foreach ( $tablet_cases as $value => $expected_class ) {
	$classes = ecs_render_dte_classes( $container_class, [
		'dte_container_type'        => 'flex',  // desktop fixat la flex
		'dte_container_type_tablet' => $value,
	] );
	$tablet_dte = array_values( array_filter( $classes, fn( $c ) => str_contains( $c, 'tablet' ) ) );

	if ( $expected_class === null ) {
		ecs_ok(
			empty( $tablet_dte ),
			"tablet='' (Inherit) → nicio clasă e-dte-tablet- adăugată"
		);
	} else {
		ecs_ok(
			in_array( $expected_class, $tablet_dte, true ),
			"tablet='$value' → clasa '$expected_class' prezentă"
		);
	}
}

// ── Test: toate 5 opțiuni pe MOBILE ──────────────────────────────────────────

ecs_section( 'container_responsive — mobile: toate 5 opțiuni' );

$mobile_cases = [
	'flex'   => 'e-dte-mobile-flex',
	'grid'   => 'e-dte-mobile-grid',
	'slider' => 'e-dte-mobile-slider',
	'custom' => 'e-dte-mobile-custom',
	''       => null,
];

foreach ( $mobile_cases as $value => $expected_class ) {
	$classes = ecs_render_dte_classes( $container_class, [
		'dte_container_type'        => 'flex',   // desktop fixat
		'dte_container_type_tablet' => 'grid',   // tablet fixat
		'dte_container_type_mobile' => $value,
	] );
	$mobile_dte = array_values( array_filter( $classes, fn( $c ) => str_contains( $c, 'mobile' ) ) );

	if ( $expected_class === null ) {
		ecs_ok(
			empty( $mobile_dte ),
			"mobile='' (Inherit) → nicio clasă e-dte-mobile- adăugată"
		);
	} else {
		ecs_ok(
			in_array( $expected_class, $mobile_dte, true ),
			"mobile='$value' → clasa '$expected_class' prezentă"
		);
	}
}

// ── Test: combinații mixte desktop/tablet/mobile ──────────────────────────────

ecs_section( 'container_responsive — combinații mixte' );

$mixed_cases = [
	// [ desktop, tablet, mobile, expected_classes ]
	[ 'flex',   'grid',   'slider', [ 'e-dte-flex', 'e-dte-tablet-grid',   'e-dte-mobile-slider' ] ],
	[ 'grid',   'custom', 'flex',   [ 'e-dte-grid', 'e-dte-tablet-custom', 'e-dte-mobile-flex'   ] ],
	[ 'slider', '',       'custom', [ 'e-dte-slider',                       'e-dte-mobile-custom' ] ],  // tablet inherit
	[ 'custom', 'flex',   '',       [ 'e-dte-custom', 'e-dte-tablet-flex'                         ] ],  // mobile inherit
	[ 'flex',   '',       '',       [ 'e-dte-flex'                                                 ] ],  // tablet+mobile inherit
	[ 'grid',   'grid',   'grid',   [ 'e-dte-grid', 'e-dte-tablet-grid',   'e-dte-mobile-grid'   ] ],  // toate identice
];

foreach ( $mixed_cases as [ $dt, $tb, $mb, $expected ] ) {
	$classes = ecs_render_dte_classes( $container_class, [
		'dte_container_type'        => $dt,
		'dte_container_type_tablet' => $tb,
		'dte_container_type_mobile' => $mb,
	] );

	$label = "desktop=$dt, tablet=" . ( $tb ?: 'inherit' ) . ", mobile=" . ( $mb ?: 'inherit' );
	$ok    = true;
	foreach ( $expected as $exp ) {
		if ( ! in_array( $exp, $classes, true ) ) {
			$ok = false;
			break;
		}
	}
	// Verificăm că nu apar clase neașteptate
	$unexpected = array_diff( $classes, $expected );
	if ( ! empty( $unexpected ) ) {
		$ok = false;
	}

	ecs_ok( $ok, "$label → " . implode( ' + ', $expected )
		. ( $ok ? '' : " — got: " . implode( ' ', $classes ) ) );
}

// ── Test: settings stocate corect per breakpoint ───────────────────────────────

ecs_section( 'container_responsive — settings stocate per breakpoint' );

$inst_settings = new $container_class(
	[
		'id'       => 'cr_settings_test',
		'settings' => [
			'dte_container_type'        => 'slider',
			'dte_container_type_tablet' => 'grid',
			'dte_container_type_mobile' => 'flex',
		],
	],
	null
);

ecs_ok( $inst_settings->get_settings( 'dte_container_type' )        === 'slider', 'settings desktop = slider' );
ecs_ok( $inst_settings->get_settings( 'dte_container_type_tablet' ) === 'grid',   'settings tablet = grid' );
ecs_ok( $inst_settings->get_settings( 'dte_container_type_mobile' ) === 'flex',   'settings mobile = flex' );

// ── Test: logica resolveType cascade (reimplementare PHP a logicii JS) ─────────

ecs_section( 'container_responsive — resolveType cascade (JS logic verificată în PHP)' );

/**
 * Reimplementare PHP a funcției resolveType() din JS:
 *
 *   desktop → numai desktop
 *   tablet  → tablet, fallback desktop
 *   mobile  → mobile, fallback tablet, fallback desktop
 *
 * '' (empty) = Inherit = sare la breakpoint-ul superior
 */
function ecs_resolve_type( array $settings, string $device ): string {
	$desktop = $settings['dte_container_type']        ?? '';
	$tablet  = $settings['dte_container_type_tablet'] ?? '';
	$mobile  = $settings['dte_container_type_mobile'] ?? '';

	switch ( $device ) {
		case 'desktop':
			return $desktop ?: 'flex'; // fallback default
		case 'tablet':
			return $tablet  ?: $desktop ?: 'flex';
		case 'mobile':
			return $mobile  ?: $tablet  ?: $desktop ?: 'flex';
	}
	return 'flex';
}

$cascade_cases = [
	// [ settings, device, expected ]
	// Cascade completă: desktop=flex, tablet=grid, mobile=slider
	[ [ 'flex', 'grid', 'slider' ], 'desktop', 'flex'   ],
	[ [ 'flex', 'grid', 'slider' ], 'tablet',  'grid'   ],
	[ [ 'flex', 'grid', 'slider' ], 'mobile',  'slider' ],

	// Tablet inherit → mobile cade la desktop
	[ [ 'flex', '', 'slider' ], 'tablet', 'flex'   ],
	[ [ 'flex', '', 'slider' ], 'mobile', 'slider' ],

	// Mobile inherit → cade la tablet
	[ [ 'flex', 'grid', '' ], 'mobile', 'grid' ],

	// Tablet și mobile inherit → cade la desktop
	[ [ 'custom', '', '' ], 'tablet', 'custom' ],
	[ [ 'custom', '', '' ], 'mobile', 'custom' ],

	// Desktop gol + tablet setat
	[ [ '', 'grid', '' ], 'desktop', 'flex'  ],  // desktop gol → default flex
	[ [ '', 'grid', '' ], 'tablet',  'grid'  ],
	[ [ '', 'grid', '' ], 'mobile',  'grid'  ],  // mobile inherit → tablet grid

	// Toate identice
	[ [ 'slider', 'slider', 'slider' ], 'desktop', 'slider' ],
	[ [ 'slider', 'slider', 'slider' ], 'tablet',  'slider' ],
	[ [ 'slider', 'slider', 'slider' ], 'mobile',  'slider' ],
];

foreach ( $cascade_cases as [ $vals, $device, $expected ] ) {
	$settings = [
		'dte_container_type'        => $vals[0],
		'dte_container_type_tablet' => $vals[1],
		'dte_container_type_mobile' => $vals[2],
	];
	$result = ecs_resolve_type( $settings, $device );
	$label  = "desktop={$vals[0]}, tablet=" . ( $vals[1] ?: 'inherit' ) . ", mobile=" . ( $vals[2] ?: 'inherit' ) . " @ $device";
	ecs_ok(
		$result === $expected,
		"resolveType: $label → $expected" . ( $result !== $expected ? " (got $result)" : '' )
	);
}

// ── Test: container_type nativ ascuns ─────────────────────────────────────────

ecs_section( 'container_responsive — container_type nativ ascuns' );

ecs_ok(
	isset( $controls['container_type'] ),
	'Control nativ container_type există (va fi ascuns via CSS)'
);

// Ascunderea se face prin update_control + CSS inline în enqueue_editor_assets().
// get_controls() strippează proprietatea 'classes' — verificăm în sursa modulului.
$src_cr_local = file_get_contents( ELECSP_DIR . 'modules/container-responsive/class-ecs-container-responsive-module.php' );
ecs_ok(
	strpos( $src_cr_local, "update_control( 'container_type'" ) !== false ||
	strpos( $src_cr_local, 'update_control( "container_type"' ) !== false,
	"update_control('container_type', ...) apelat în add_container_type_control()"
);
ecs_ok(
	strpos( $src_cr_local, 'dte-hidden-control' ) !== false,
	"Clasa 'dte-hidden-control' folosită pentru ascunderea container_type"
);
ecs_ok(
	strpos( $src_cr_local, '.elementor-control-container_type' ) !== false &&
	strpos( $src_cr_local, 'display: none !important' ) !== false,
	'CSS inline ascunde .elementor-control-container_type { display: none !important }'
);

// ── Restaurare ──────────────────────────────────────────────────────────────

$manager->set_active( $original_active );
ecs_ok(
	get_option( 'ecs_active_modules', [] ) === $original_active,
	'Starea originală restaurată'
);
