<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/users.php,v 1.6 2006/04/11 13:09:28 squareing Exp $
 *
 * Copyright (c) 2005 bitweaver.org
 * All Rights Reserved. See copyright.txt for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details
 *
 * $Id: users.php,v 1.6 2006/04/11 13:09:28 squareing Exp $
 * @package stats
 * @subpackage functions
 */
/**
 * Required files
 */
require_once( '../bit_setup_inc.php' );
include_once( STATS_PKG_PATH.'stats_lib.php' );

global $statslib, $gBitSystem;

$gBitSystem->verifyPackage( 'stats' );

$gBitSystem->verifyPermission( 'p_stats_view' );

if (!isset($_REQUEST["period"])) {
	$_REQUEST["period"] = 'month';
}

$gBitSmarty->assign( 'period', $_REQUEST["period"] );

$stats = $statslib->registrationStats( $_REQUEST["period"] );

$gBitSmarty->assign_by_ref( 'userStats', $stats );

// Display the template
$gBitSystem->display( 'bitpackage:stats/user_stats.tpl');

?>