<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/users.php,v 1.2 2005/07/17 17:36:17 squareing Exp $
 *
 * Copyright (c) 2005 bitweaver.org
 * All Rights Reserved. See copyright.txt for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details
 *
 * $Id: users.php,v 1.2 2005/07/17 17:36:17 squareing Exp $
 * @package stats
 * @subpackage functions
 */

require_once( '../bit_setup_inc.php' );

include_once( STATS_PKG_PATH.'stats_lib.php' );
global $statslib, $gBitSystem;

$gBitSystem->verifyPackage( 'stats' );

$gBitSystem->verifyPermission( 'bit_p_view_stats' );

if (!isset($_REQUEST["period"])) {
	$_REQUEST["period"] = 86400;
}

switch( $_REQUEST["period"] ) {
	case 2592000:
		$periodName = '30 Days';
		break;
	case 604800:
		$periodName = 'Weekly';
		break;
	case 86400:
		$periodName = 'Daily';
		break;
}
$smarty->assign( 'periodName', $periodName );

$stats = $statslib->registrationStats( $_REQUEST["period"] );

$smarty->assign_by_ref( 'userStats', $stats );

// Display the template
$gBitSystem->display( 'bitpackage:stats/user_stats.tpl');

?>