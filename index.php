<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/index.php,v 1.9 2010/02/08 21:27:25 wjames5 Exp $
 *
 * $Id: index.php,v 1.9 2010/02/08 21:27:25 wjames5 Exp $
 * @package stats
 * @subpackage functions
 */

/**
 * required setup
 */
require_once( '../kernel/setup_inc.php' );
include_once( STATS_PKG_PATH.'Statistics.php' );

$gBitSystem->verifyPackage( 'stats' );
$gBitSystem->verifyPermission( 'p_stats_view' );

$gStats = new Statistics();

$gBitSmarty->assign( 'siteStats', $gStats->getSiteStats() );
$gBitSmarty->assign( 'contentOverview', $gStats->getContentOverview( $_REQUEST ));
$gBitSmarty->assign( 'contentStats', $gStats->getContentStats() );

$gBitSystem->display( 'bitpackage:stats/stats.tpl', tra( "Statistics" ) , array( 'display_mode' => 'display' ));
?>
