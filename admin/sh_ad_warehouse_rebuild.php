<?php
/**
 * Wipe derived ad warehouse rows, pull Google campaign metrics for a date
 * window, then attribute from first-touch landings already on this database.
 *
 * Does not read httpd logs (see the products log importer) and never uploads
 * conversions. Keeps stats_prefs and stats_ad_network.
 *
 *   php stats/admin/sh_ad_warehouse_rebuild.php --site_name=example --since=2026-01-01 --wipe
 *
 * Live (non-dev host): add --live
 */
$here = dirname( __FILE__ );
$gShellScript = true;
$gLightweightScan = true;
foreach( array( 'IS_DEV', 'IS_LIVE', 'IS_SANDBOX', 'SITE_NAME' ) as $k ) {
	$v = getenv( $k );
	if( $v !== false && $v !== '' && empty( $_SERVER[$k] ) ) {
		$_SERVER[$k] = $v;
	}
}

$since = '2026-01-01';
$until = date( 'Y-m-d', strtotime( '-1 day' ) );
$wipe = false;
$live = false;
$skipPull = false;
$skipBackfill = false;
$siteArg = null;
foreach( $argv as $i => $arg ) {
	if( $i === 0 ) {
		continue;
	}
	if( strpos( $arg, '--since=' ) === 0 ) {
		$since = substr( $arg, 8 );
	} elseif( strpos( $arg, '--until=' ) === 0 ) {
		$until = substr( $arg, 8 );
	} elseif( $arg === '--wipe' ) {
		$wipe = true;
	} elseif( $arg === '--live' ) {
		$live = true;
	} elseif( $arg === '--skip-pull' ) {
		$skipPull = true;
	} elseif( $arg === '--skip-backfill' ) {
		$skipBackfill = true;
	} elseif( strpos( $arg, '--site_name=' ) === 0 ) {
		$siteArg = $arg;
	} elseif( $arg === '--upload' ) {
		fwrite( STDERR, "Warehouse scripts never upload conversions.\n" );
		exit( 2 );
	} elseif( $arg === '--help' || $arg === '-h' ) {
		fwrite( STDERR, "Usage: $argv[0] --site_name=example --since=YYYY-MM-DD [--until=YYYY-MM-DD] --wipe [--live]\n" );
		exit( 0 );
	}
}

if( !preg_match( '/^\d{4}-\d{2}-\d{2}$/', $since ) || !preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until ) ) {
	fwrite( STDERR, "Dates must be YYYY-MM-DD\n" );
	exit( 1 );
}
if( $since > $until ) {
	fwrite( STDERR, "--since must be on or before --until\n" );
	exit( 1 );
}
if( !$wipe ) {
	fwrite( STDERR, "Refusing to wipe without --wipe\n" );
	exit( 2 );
}

require_once dirname( __FILE__ ).'/../../config/kernel/cron_setup_inc.php';
require_once STATS_PKG_INCLUDE_PATH.'ads_api_lib.php';
require_once STATS_PKG_INCLUDE_PATH.'ads_warehouse_lib.php';
ads_refuse_upload( $argv );

$isDev = !empty( $_SERVER['IS_DEV'] );
if( !$isDev && !$live ) {
	fwrite( STDERR, "Live host: pass --live to wipe and rebuild this database.\n" );
	exit( 2 );
}

$row = ads_require_warehouse_db( $_SERVER );
fwrite( STDERR, "rebuild warehouse on {$row['addr']} {$row['db']} spend $since..$until\n" );
ads_apply_warehouse_schema();
ads_wipe_warehouse_derived();
fwrite( STDERR, "wiped derived warehouse (prefs and network kept)\n" );

if( !$skipPull ) {
	$pull = $here.'/sh_ad_warehouse_pull.php';
	$common = array();
	if( $siteArg ) {
		$common[] = $siteArg;
	}
	ads_cli_run( $pull, array_merge( $common, array( '--entities-only' ) ) );
	$cursor = new DateTime( $since.' UTC' );
	$end = new DateTime( $until.' UTC' );
	while( $cursor <= $end ) {
		$chunkUntil = clone $cursor;
		$chunkUntil->modify( 'last day of this month' );
		if( $chunkUntil > $end ) {
			$chunkUntil = clone $end;
		}
		ads_cli_run( $pull, array_merge( $common, array(
			'--metrics-only',
			'--metrics=campaign',
			'--since='.$cursor->format( 'Y-m-d' ),
			'--until='.$chunkUntil->format( 'Y-m-d' ),
		) ) );
		$cursor->modify( 'first day of next month' );
	}
}

if( !$skipBackfill ) {
	$bf = array();
	if( $siteArg ) {
		$bf[] = $siteArg;
	}
	ads_cli_run( $here.'/sh_ad_warehouse_backfill.php', $bf );
}

fwrite( STDERR, "warehouse rebuild complete $since..$until\n" );
fwrite( STDERR, "Compare network ROAS to cohort LTV on ad_roas.php?cohort=1&since=$since&until=$until\n" );
