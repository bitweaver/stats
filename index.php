<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/index.php,v 1.5 2006/02/02 09:14:14 squareing Exp $
 *
 * Copyright (c) 2004 bitweaver.org
 * Copyright (c) 2003 tikwiki.org
 * Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See copyright.txt for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details
 *
 * $Id: index.php,v 1.5 2006/02/02 09:14:14 squareing Exp $
 * @package stats
 * @subpackage functions
 */

/**
 * required setup
 */
require_once( '../bit_setup_inc.php' );

include_once( STATS_PKG_PATH.'stats_lib.php' );
global $statslib, $gBitSystem;

$gBitSystem->verifyPackage( 'stats' );

$gBitSystem->verifyPermission( 'bit_p_view_stats' );

if (!isset($_REQUEST["days"])) {
	$_REQUEST["days"] = 7;
}

$gBitSmarty->assign('pv_chart', 'n');

if (isset($_REQUEST["pv_chart"])) {
	$gBitSmarty->assign('pv_chart', 'y');
}

$gBitSmarty->assign('days', $_REQUEST["days"]);

$gBitSmarty->assign('usage_chart', 'n');

if (isset($_REQUEST["chart"])) {
	$gBitSmarty->assign($_REQUEST["chart"] . "_chart", 'y');
}

if ($gBitSystem->isPackageActive( 'wiki' ) ) {
	$wiki_stats = $statslib->wiki_stats();
} else {
	$wiki_stats = false;
}
$gBitSmarty->assign_by_ref('wiki_stats', $wiki_stats);

if ($gBitSystem->isPackageActive( 'articles' ) ) {
	$cms_stats = $statslib->cms_stats();
} else {
  $cms_stats = false;
}
$gBitSmarty->assign_by_ref('cms_stats', $cms_stats);

if ($gBitSystem->isPackageActive( 'blogs' ) ) {
	$blog_stats = $statslib->blog_stats();
} else {
  $blog_stats =	false;
}
$gBitSmarty->assign_by_ref('blog_stats', $blog_stats);

$user_stats = $statslib->user_stats();
$gBitSmarty->assign_by_ref('user_stats', $user_stats);

$site_stats = $statslib->site_stats();
$gBitSmarty->assign_by_ref('site_stats', $site_stats);

// Display the template
$gBitSystem->display( 'bitpackage:stats/stats.tpl', tra( "Statistics" ) );
?>
