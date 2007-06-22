<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/index.php,v 1.7 2007/06/22 12:35:26 squareing Exp $
 *
 * $Id: index.php,v 1.7 2007/06/22 12:35:26 squareing Exp $
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

$gBitSystem->display( 'bitpackage:stats/stats.tpl', tra( "Statistics" ) );
?>
