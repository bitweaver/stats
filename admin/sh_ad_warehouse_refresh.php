<?php
/**
 * One-shot warehouse refresh for this site checkout: schema, Google pull, attribution.
 * After deploy + Ad API setup, this is the only command needed post-clone.
 *
 *   php stats/admin/sh_ad_warehouse_refresh.php --site_name=example --full
 *
 * Nightly (incremental):
 *   php stats/admin/sh_ad_warehouse_pull.php --metrics=campaign --metrics-only
 *   php stats/admin/sh_ad_warehouse_backfill.php
 *
 * Optional: --import-file=/path/to/secrets  (key: value lines; SA JSON path is inlined)
 * No conversion upload.
 */
$here = dirname( __FILE__ );
$import = null;
$full = false;
$skipPull = false;
$skipBackfill = false;
$args = array();
foreach( $argv as $i => $arg ) {
	if( $i === 0 ) {
		continue;
	}
	if( strpos( $arg, '--import-file=' ) === 0 ) {
		$import = substr( $arg, 14 );
	} elseif( $arg === '--full' ) {
		$full = true;
	} elseif( $arg === '--skip-pull' ) {
		$skipPull = true;
	} elseif( $arg === '--skip-backfill' ) {
		$skipBackfill = true;
	} elseif( $arg === '--upload' ) {
		fwrite( STDERR, "Warehouse scripts never upload conversions.\n" );
		exit( 2 );
	} else {
		$args[] = $arg;
	}
}

$php = ( defined( 'PHP_BINARY' ) && PHP_BINARY ) ? PHP_BINARY : 'php';

function ads_run_child( $php, $script, $extra ) {
	$cmd = escapeshellcmd( $php ).' '.escapeshellarg( $script );
	foreach( $extra as $a ) {
		$cmd .= ' '.escapeshellarg( $a );
	}
	fwrite( STDERR, "+ $cmd\n" );
	passthru( $cmd, $code );
	if( $code !== 0 ) {
		fwrite( STDERR, "failed $script exit $code\n" );
		exit( $code );
	}
}

if( $import ) {
	chdir( $here );
	$gShellScript = true;
	$gLightweightScan = true;
	foreach( array( 'IS_DEV', 'IS_LIVE', 'IS_SANDBOX', 'SITE_NAME' ) as $k ) {
		$v = getenv( $k );
		if( $v !== false && $v !== '' && empty( $_SERVER[$k] ) ) {
			$_SERVER[$k] = $v;
		}
	}
	require_once dirname( __FILE__ ).'/../../config/kernel/cron_setup_inc.php';
	require_once STATS_PKG_INCLUDE_PATH.'ads_api_lib.php';
	require_once STATS_PKG_INCLUDE_PATH.'ads_warehouse_lib.php';
	ads_require_warehouse_db( $_SERVER );
	ads_apply_warehouse_schema();
	$n = ads_import_secrets_file( $import );
	fwrite( STDERR, "imported $n ad API keys into stats_prefs (values not printed)\n" );
}

$pullArgs = $args;
if( $full ) {
	$pullArgs[] = '--full';
	$pullArgs[] = '--metrics=campaign';
}
if( !$skipPull ) {
	ads_run_child( $php, $here.'/sh_ad_warehouse_pull.php', $pullArgs );
}
if( !$skipBackfill ) {
	$bf = array();
	foreach( $argv as $arg ) {
		if( strpos( $arg, '--site_name=' ) === 0 ) {
			$bf[] = $arg;
		}
	}
	ads_run_child( $php, $here.'/sh_ad_warehouse_backfill.php', $bf );
}
fwrite( STDERR, "warehouse refresh complete\n" );
