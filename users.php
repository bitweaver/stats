<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/users.php,v 1.7 2007/06/22 12:35:26 squareing Exp $
 *
 * Copyright (c) 2005 bitweaver.org
 * All Rights Reserved. See copyright.txt for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details
 *
 * $Id: users.php,v 1.7 2007/06/22 12:35:26 squareing Exp $
 * @package stats
 * @subpackage functions
 */
/**
 * Required files
 */
require_once( '../bit_setup_inc.php' );
include_once( STATS_PKG_PATH.'stats_lib.php' );

$stats = new Statistics();

$gBitSystem->verifyPackage( 'stats' );
$gBitSystem->verifyPermission( 'p_stats_view' );

if( !isset( $_REQUEST["period"] )) {
	$_REQUEST["period"] = 'month';
}

$gBitSmarty->assign( 'userStats', $stats->registrationStats( $_REQUEST["period"] ));
$gBitSmarty->assign( 'period', $_REQUEST["period"] );

// Display the template
$gBitSystem->display( 'bitpackage:stats/user_stats.tpl');
?>
