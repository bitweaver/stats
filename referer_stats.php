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

$gBitSmarty->assign( 'referers', $gStats->getRefererList( $_REQUEST ));
$gBitSmarty->assign( 'listInfo', $_REQUEST['listInfo'] );
$gBitSystem->display( 'bitpackage:stats/referer_stats.tpl', tra( 'Referer Statistics' ), array( 'display_mode' => 'display' ));
?>
