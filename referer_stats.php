<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/referer_stats.php,v 1.12 2010/02/08 21:27:25 wjames5 Exp $
 *
 * $Id: referer_stats.php,v 1.12 2010/02/08 21:27:25 wjames5 Exp $
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
