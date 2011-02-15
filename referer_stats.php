<?php
/**
 * $Header$
 *
 * $Id$
 * @package stats
 */

/**
 * required setup
 */
require_once( '../kernel/setup_inc.php' );
include_once ( STATS_PKG_PATH.'Statistics.php');

$gBitSystem->verifyPackage( 'stats' );
$gBitSystem->verifyFeature( 'stats_referers' );
$gBitSystem->verifyPermission( 'p_stats_view_referer' );

$gStats = new Statistics();

// get rid of all referers in the database
if( isset( $_REQUEST["clear"] )) {
	$gStats->expungeReferers();
}
$referers = $gStats->getRefererList( $_REQUEST );
$totalRegistrations = 0;
$maxRegistrations = 0;
foreach( array_keys( $referers ) as $k ) {
	$kCount = count( $referers[$k] );
	$totalRegistrations += $kCount;
	if( $kCount > $maxRegistrations ) {
		$maxRegistrations = $kCount;
	}
}

$gBitSmarty->assign_by_ref( 'referers', $referers );
$gBitSmarty->assign( 'totalRegistrations', $totalRegistrations );
$gBitSmarty->assign( 'maxRegistrations', $maxRegistrations );
$gBitSmarty->assign( 'listInfo', $_REQUEST['listInfo'] );
$gBitSystem->display( 'bitpackage:stats/referer_stats.tpl', tra( 'Referer Statistics' ), array( 'display_mode' => 'display' ));
?>
