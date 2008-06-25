<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/index.php,v 1.8 2008/06/25 22:21:24 spiderr Exp $
 *
 * $Id: index.php,v 1.8 2008/06/25 22:21:24 spiderr Exp $
 * @package stats
 * @subpackage functions
 */

/**
 * required setup
 */
require_once( '../bit_setup_inc.php' );
include_once( STATS_PKG_PATH.'Statistics.php' );

$gBitSystem->verifyPackage( 'stats' );
$gBitSystem->verifyPermission( 'p_stats_view' );

$gStats = new Statistics();

$gBitSmarty->assign( 'siteStats', $gStats->getSiteStats() );
$gBitSmarty->assign( 'contentOverview', $gStats->getContentOverview( $_REQUEST ));
$gBitSmarty->assign( 'contentStats', $gStats->getContentStats() );

$gBitSystem->display( 'bitpackage:stats/stats.tpl', tra( "Statistics" ) , array( 'display_mode' => 'display' ));
?>
