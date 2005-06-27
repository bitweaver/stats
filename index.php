<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/index.php,v 1.1.1.1.2.1 2005/06/27 15:56:42 lsces Exp $
 *
 * Copyright (c) 2004 bitweaver.org
 * Copyright (c) 2003 tikwiki.org
 * Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See copyright.txt for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details
 *
 * $Id: index.php,v 1.1.1.1.2.1 2005/06/27 15:56:42 lsces Exp $
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

$smarty->assign('pv_chart', 'n');

if (isset($_REQUEST["pv_chart"])) {
	$smarty->assign('pv_chart', 'y');
}

$smarty->assign('days', $_REQUEST["days"]);

$smarty->assign('usage_chart', 'n');

if (isset($_REQUEST["chart"])) {
	$smarty->assign($_REQUEST["chart"] . "_chart", 'y');
}

if ($gBitSystem->isPackageActive( 'wiki' ) ) {
	$wiki_stats = $statslib->wiki_stats();
} else {
	$wiki_stats = false;
}
$smarty->assign_by_ref('wiki_stats', $wiki_stats);

if ($gBitSystem->isPackageActive( 'imagegals' ) ) {
	$igal_stats = $statslib->image_gal_stats();
} else {
	$igal_stats = false;
}
$smarty->assign_by_ref('igal_stats', $igal_stats);

if ($gBitSystem->isPackageActive( 'filegals' ) ) {
	$fgal_stats = $statslib->file_gal_stats();
} else {
  $fgal_stats =	false;
}
$smarty->assign_by_ref('fgal_stats', $fgal_stats);

if ($gBitSystem->isPackageActive( 'articles' ) ) {
	$cms_stats = $statslib->cms_stats();
} else {
  $cms_stats = false;
}
$smarty->assign_by_ref('cms_stats', $cms_stats);

if ($gBitSystem->isPackageActive( 'forums' ) ) {
	$forum_stats = $statslib->forum_stats();
} else {
	$forum_stats = false;
}
$smarty->assign_by_ref('forum_stats', $forum_stats);

if ($gBitSystem->isPackageActive( 'blogs' ) ) {
	$blog_stats = $statslib->blog_stats();
} else {
  $blog_stats =	false;
}
$smarty->assign_by_ref('blog_stats', $blog_stats);

if ($gBitSystem->isPackageActive( 'polls' ) ) {
	$poll_stats = $statslib->poll_stats();
} else {
	$poll_stats = false;
}
$smarty->assign_by_ref('poll_stats', $poll_stats);

if ($gBitSystem->isPackageActive( 'faqs' ) ) {
	$faq_stats = $statslib->faq_stats();
} else {
	$faq_stats = false;
}
$smarty->assign_by_ref('faq_stats', $faq_stats);

if ($gBitSystem->isPackageActive( 'quizzes' ) ) {
	$quiz_stats = $statslib->quiz_stats();
} else {
	$quiz_stats = false;
}
$smarty->assign_by_ref('quiz_stats', $quiz_stats);


$user_stats = $statslib->user_stats();
$smarty->assign_by_ref('user_stats', $user_stats);

$site_stats = $statslib->site_stats();
$smarty->assign_by_ref('site_stats', $site_stats);



// Display the template
$gBitSystem->display( 'bitpackage:stats/stats.tpl');

?>
