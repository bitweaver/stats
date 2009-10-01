<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/users.php,v 1.11 2009/10/01 14:17:05 wjames5 Exp $
 *
 * Copyright (c) 2005 bitweaver.org
 * All Rights Reserved. See below for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details
 *
 * $Id: users.php,v 1.11 2009/10/01 14:17:05 wjames5 Exp $
 * @package stats
 * @subpackage functions
 */
/**
 * Required files
 */
require_once( '../bit_setup_inc.php' );
require_once( STATS_PKG_PATH.'Statistics.php' );

$stats = new Statistics();

$gBitSystem->verifyPackage( 'stats' );
$gBitSystem->verifyPermission( 'p_stats_view' );

if( !isset( $_REQUEST["period"] )) {
	$_REQUEST["period"] = 'month';
}

$gBitSmarty->assign( 'userStats', $stats->registrationStats( $_REQUEST["period"] ));
$gBitSmarty->assign( 'period', $_REQUEST["period"] );

// Display the template
$gBitSystem->display( 'bitpackage:stats/user_stats.tpl', NULL, array( 'display_mode' => 'display' ));
?>
