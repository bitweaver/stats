<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/users.php,v 1.12 2009/11/11 17:38:46 spiderr Exp $
 *
 * Copyright (c) 2005 bitweaver.org
 * All Rights Reserved. See below for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details
 *
 * $Id: users.php,v 1.12 2009/11/11 17:38:46 spiderr Exp $
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

switch( $_REQUEST["period"] ) {
	case 'year':
		$format = 'Y';
		break;
	case 'quarter':
		$format = 'Y-\QQ';
		break;
	case 'day':
		$format = 'Y-m-d';
		break;
	case 'week':
		$format = 'Y \Week W';
		break;
	case 'month':
	default:
		$format = 'Y-m';
		break;
}

if( !empty( $_REQUEST['itemize'] ) ) {
	$listHash['registration_period'] = $_REQUEST['itemize'];
	$listHash['registration_period_format'] = $format;
	bit_redirect( USERS_PKG_URL.'admin/index.php?registration_period='.urlencode( $_REQUEST['itemize'] ).'&registration_period_format='.urlencode( $listHash['registration_period_format'] ) );
}

$gBitSmarty->assign( 'userStats', $stats->registrationStats( $format ));
$gBitSmarty->assign( 'period', $_REQUEST["period"] );

// Display the template
$gBitSystem->display( 'bitpackage:stats/user_stats.tpl', NULL, array( 'display_mode' => 'display' ));
?>
