<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/referer_stats.php,v 1.1.1.1.2.3 2005/08/14 11:45:19 wolff_borg Exp $
 *
 * Copyright (c) 2004 bitweaver.org
 * Copyright (c) 2003 tikwiki.org
 * Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See copyright.txt for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details
 *
 * $Id: referer_stats.php,v 1.1.1.1.2.3 2005/08/14 11:45:19 wolff_borg Exp $
 * @package stats
 * @subpackage functions
 */

/**
 * required setup
 */
require_once( '../bit_setup_inc.php' );

include_once ( STATS_PKG_PATH.'stats_lib.php');

$gBitSystem->verifyPackage( 'stats' );
$gBitSystem->verifyFeature( 'feature_referer_stats' );
$gBitSystem->verifyPermission( 'bit_p_view_referer_stats' );


if (isset($_REQUEST["clear"])) {
	
	$statslib->clear_referer_stats();
}

/*
if($bit_p_take_quiz != 'y') {
	$gBitSmarty->assign('msg',tra("You dont have permission to use this feature"));
	$gBitSystem->display( 'error.tpl' );
	die;
}
*/
if ( empty( $_REQUEST["sort_mode"] ) ) {
	$sort_mode = 'hits_desc';
} else {
	$sort_mode = $_REQUEST["sort_mode"];
}

if (!isset($_REQUEST["offset"])) {
	$offset = 0;
} else {
	$offset = $_REQUEST["offset"];
}

$gBitSmarty->assign_by_ref('offset', $offset);

if (isset($_REQUEST["find"])) {
	$find = $_REQUEST["find"];
} else {
	$find = '';
}

$gBitSmarty->assign('find', $find);

$gBitSmarty->assign_by_ref('sort_mode', $sort_mode);
$referers = $statslib->list_referer_stats($offset, $maxRecords, $sort_mode, $find);

$cant_pages = ceil($referers["cant"] / $maxRecords);
$gBitSmarty->assign_by_ref('cant_pages', $cant_pages);
$gBitSmarty->assign('actual_page', 1 + ($offset / $maxRecords));

if ($referers["cant"] > ($offset + $maxRecords)) {
	$gBitSmarty->assign('next_offset', $offset + $maxRecords);
} else {
	$gBitSmarty->assign('next_offset', -1);
}

// If offset is > 0 then prev_offset
if ($offset > 0) {
	$gBitSmarty->assign('prev_offset', $offset - $maxRecords);
} else {
	$gBitSmarty->assign('prev_offset', -1);
}

$gBitSmarty->assign_by_ref('referers', $referers["data"]);



// Display the template
$gBitSystem->display( 'bitpackage:stats/referer_stats.tpl');

?>
