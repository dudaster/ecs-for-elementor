<?php
/**
 * ECS Test Runner
 * Usage: wp eval-file .../tests/runner.php --allow-root
 */

$tests = [];

function ecs_ok( bool $cond, string $msg ): void {
	static $pass = 0, $fail = 0;
	if ( func_num_args() === 0 ) { return; } // unused
	if ( $cond ) { echo "  \e[32m✓\e[0m $msg\n"; $pass++; }
	else         { echo "  \e[31m✗\e[0m $msg\n"; $fail++; }
	// Store in a global-accessible way
	$GLOBALS['ecs_pass'] = $pass;
	$GLOBALS['ecs_fail'] = $fail;
}

function ecs_section( string $title ): void {
	echo "\n\e[1m$title\e[0m\n" . str_repeat('─', 60) . "\n";
}

function ecs_suite( string $file ): void {
	global $tests;
	$label = basename( $file, '.php' );
	echo "\n\e[1;34m══ $label \e[0m\n";
	require $file;
	$tests[] = $label;
}

$dir = __DIR__;
ecs_suite( "$dir/test-1-registration.php" );
ecs_suite( "$dir/test-2-hooks.php" );
ecs_suite( "$dir/test-3-admin.php" );
ecs_suite( "$dir/test-4-color-scheme.php" );
ecs_suite( "$dir/test-5-style-templates.php" );
ecs_suite( "$dir/test-6-editorial-text.php" );
ecs_suite( "$dir/test-7-container-layout.php" );
ecs_suite( "$dir/test-8-mobile-menu.php" );
ecs_suite( "$dir/test-9-pro-modules.php" );
ecs_suite( "$dir/test-10-container-responsive.php" );

echo "\n" . str_repeat('═', 60) . "\n";
$pass  = $GLOBALS['ecs_pass'] ?? 0;
$fail  = $GLOBALS['ecs_fail'] ?? 0;
$total = $pass + $fail;
$color = $fail === 0 ? '32' : '31';
echo "\e[{$color}mRezultat: $pass/$total passed" . ( $fail ? ", $fail FAILED" : '' ) . "\e[0m\n\n";
exit( $fail > 0 ? 1 : 0 );
