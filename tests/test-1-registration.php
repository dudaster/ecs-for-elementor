<?php
/**
 * Test 1 — Module Registration & Identity
 * Verifică că fiecare modul free/pro e înregistrat corect cu ID, titlu, flag-uri.
 */

$manager = ECS_Core::instance()->modules();

// ── Free modules ──────────────────────────────────────────────────────────────

ecs_section( 'Free modules — înregistrare' );

$expected_free = [
	'legacy'          => [ 'deprecated' => true,  'pro' => false ],
	'color_scheme'    => [ 'deprecated' => false, 'pro' => false ],
	'container_layout'=> [ 'deprecated' => false, 'pro' => false ],
	'mobile_menu'     => [ 'deprecated' => false, 'pro' => false ],
	'editorial_text'  => [ 'deprecated' => false, 'pro' => false ],
	'style_templates' => [ 'deprecated' => false, 'pro' => false ],
];

foreach ( $expected_free as $id => $flags ) {
	$module = $manager->get( $id );
	ecs_ok( $module !== null,                     "$id — modul găsit" );
	ecs_ok( $module && $module->get_id() === $id, "$id — get_id() corect" );
	ecs_ok( $module && ! empty( $module->get_title() ),       "$id — are titlu" );
	ecs_ok( $module && ! empty( $module->get_description() ), "$id — are descriere" );
	ecs_ok( $module && $module->is_pro()        === $flags['pro'],        "$id — is_pro() = " . var_export( $flags['pro'], true ) );
	ecs_ok( $module && $module->is_deprecated() === $flags['deprecated'], "$id — is_deprecated() = " . var_export( $flags['deprecated'], true ) );
}

// ── Pro modules ───────────────────────────────────────────────────────────────

$pro_active = defined( 'ELECSP_VER' );

ecs_section( 'Pro modules — înregistrare' . ( $pro_active ? '' : ' (Pro INACTIV — skip)' ) );

$expected_pro = [
	'legacy_pro'          => [ 'deprecated' => true,  'pro' => true ],
	'color_schemes'       => [ 'deprecated' => false, 'pro' => true ],
	'font_schemes'        => [ 'deprecated' => false, 'pro' => true ],
	'alt_logos'           => [ 'deprecated' => false, 'pro' => true ],
	'custom_look_feel'    => [ 'deprecated' => false, 'pro' => true ],
	'container_responsive'=> [ 'deprecated' => false, 'pro' => true ],
];

if ( $pro_active ) {
	foreach ( $expected_pro as $id => $flags ) {
		$module = $manager->get( $id );
		ecs_ok( $module !== null,                     "$id — modul găsit" );
		ecs_ok( $module && $module->get_id() === $id, "$id — get_id() corect" );
		ecs_ok( $module && ! empty( $module->get_title() ),       "$id — are titlu" );
		ecs_ok( $module && ! empty( $module->get_description() ), "$id — are descriere" );
		ecs_ok( $module && $module->is_pro() === true,            "$id — is_pro() = true" );
	}
} else {
	ecs_ok( true, 'Skip — ECS Pro nu este activ' );
}

// ── Manager state ─────────────────────────────────────────────────────────────

ecs_section( 'Modules manager — stare generală' );

$all = $manager->get_all();
ecs_ok( count( $all ) >= 6, 'get_all() returnează cel puțin 6 module (' . count( $all ) . ' găsite)' );

$active_ids = array_filter( array_keys( $expected_free ), fn( $id ) => $manager->is_active( $id ) );
ecs_ok( count( $active_ids ) >= 1, 'Cel puțin un modul free este activ (' . implode( ', ', $active_ids ) . ')' );

// Testăm că set_active() + is_active() funcționează (toggle temporar pe un modul)
$test_id     = 'editorial_text';
$was_active  = $manager->is_active( $test_id );
$new_active  = $was_active
	? array_diff( $active_ids, [ $test_id ] )
	: array_merge( $active_ids, [ $test_id ] );

$manager->set_active( $new_active );
ecs_ok( $manager->is_active( $test_id ) === ! $was_active, "set_active() schimbă starea lui $test_id" );

// Restaurăm starea originală
$manager->set_active( $active_ids );
ecs_ok( $manager->is_active( $test_id ) === $was_active, "set_active() restaurează starea originală" );
