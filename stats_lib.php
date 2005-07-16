<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/Attic/stats_lib.php,v 1.1.1.1.2.2 2005/07/16 17:55:40 spiderr Exp $
 *
 * Copyright (c) 2004 bitweaver.org
 * Copyright (c) 2003 tikwiki.org
 * Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See copyright.txt for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details
 *
 * $Id: stats_lib.php,v 1.1.1.1.2.2 2005/07/16 17:55:40 spiderr Exp $
 * @package stats
 */

/**
 * @package stats
 * @subpackage StatsLib
 */
class StatsLib extends BitBase {
	function StatsLib() {					BitBase::BitBase();
	}


	/* referer methods */
	function clear_referer_stats() {
		$query = "delete from tiki_referer_stats";

		$result = $this->query($query);
	}

	function list_referer_stats($offset, $maxRecords, $sort_mode, $find) {
		$bindvars = array();
		if ($find) {
			$findesc = $this->qstr('%' . strtoupper( $find ) . '%');
			$mid = " where (UPPER(`referer`) like ?)";
			$bindvars = array($findesc);
		} else {
			$mid = "";
		}

		$query = "select * from `".BIT_DB_PREFIX."tiki_referer_stats` $mid order by ".$this->convert_sortmode($sort_mode);;
		$query_cant = "select count(*) from `".BIT_DB_PREFIX."tiki_referer_stats` $mid";
		$result = $this->query($query,$bindvars,$maxRecords,$offset);
		$cant = $this->getOne($query_cant,$bindvars);
		$ret = array();

		while ($res = $result->fetchRow()) {
			$ret[] = $res;
		}

		$retval = array();
		$retval["data"] = $ret;
		$retval["cant"] = $cant;
		return $retval;
	}

	function register_referer($referer) {
		if( !empty( $referer ) ) {
			$now = date("U");
			$cant = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_referer_stats` where `referer`=?",array($referer));

			$query = "update `".BIT_DB_PREFIX."tiki_referer_stats` set `hits`=`hits`+1,`last`=? where `referer`=?";
			$rs = $this->query($query,array((int)$now,$referer));
			if( !$this->mDb->mDb->Affected_Rows() ) {
				$query = "insert into `".BIT_DB_PREFIX."tiki_referer_stats`(`last`,`referer`,`hits`) values(?,?,1)";
				$result = $this->query($query,array((int)$now,$referer));
			}
		}
	}


	/* content methods */
	function list_orphan_pages($offset = 0, $maxRecords = -1, $sort_mode = 'title_desc', $find = '') {

		if ($sort_mode == 'size_desc') {
			$sort_mode = 'page_size_desc';
		}

		if ($sort_mode == 'size_asc') {
			$sort_mode = 'page_size_asc';
		}

		$old_sort_mode = '';

		if (in_array($sort_mode, array(
			'versions_desc',
			'versions_asc',
			'links_asc',
			'links_desc',
			'backlinks_asc',
			'backlinks_desc'
		))) {
			$old_offset = $offset;

			$old_maxRecords = $maxRecords;
			$old_sort_mode = $sort_mode;
			$sort_mode = 'user_desc';
			$offset = 0;
			$maxRecords = -1;
		}
		$bindvars = array();
		if ($find) {
			$mid = " where UPPER(`page_name`) like ? ";
			$bindvars[] = '%'.strtoupper( $find ).'%';
		} else {
			$mid = "";
		}

		// If sort mode is versions then offset is 0, maxRecords is -1 (again) and sort_mode is nil
		// If sort mode is links then offset is 0, maxRecords is -1 (again) and sort_mode is nil
		// If sort mode is backlinks then offset is 0, maxRecords is -1 (again) and sort_mode is nil
		$query = "select * from `".BIT_DB_PREFIX."tiki_pages` tp INNER JOIN `".BIT_DB_PREFIX."tiki_content` tc ON( tc.`content_id`=tp.`content_id` ) $mid order by ".$this->convert_sortmode($sort_mode);
		$query_cant = "select count(*) from `".BIT_DB_PREFIX."tiki_pages` $mid";
		$result = $this->query($query,$bindvars,-1,0);
		$cant = $this->getOne($query_cant,$bindvars);
		$ret = array();
		$num_or = 0;

		while ($res = $result->fetchRow()) {
			$title = $res["title"];
			$queryc = "select count(*) from `".BIT_DB_PREFIX."tiki_links` where `to_content_id`=?";
			$cant = $this->getOne( $queryc,array($res['content_id'] ) );
			$queryc = "select count(*) from `".BIT_DB_PREFIX."tiki_structures` ts WHERE ts.`content_id`=?";
			$cant += $this->getOne( $queryc, array( $res['content_id'] ) );

			if ($cant == 0) {
				$num_or++;
				$aux = array();
				$aux["title"] = $title;
				$page = $aux["title"];
				$page_as = addslashes($page);
				$aux["hits"] = $res["hits"];
				$aux["last_modified"] = $res["last_modified"];
				$aux["user_id"] = $res["user_id"];
				$aux["ip"] = $res["ip"];
//				$aux["len"] = $res["len"];
				$aux["comment"] = $res["comment"];
				$aux["version"] = $res["version"];
				$aux["flag"] = $res["flag"] == 'y' ? tra('locked') : tra('unlocked');
				$aux["versions"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_history` WHERE `page_id`=?",array($res['page_id']));
				$aux["links"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_links` WHERE `from_content_id`=?",array( $res['content_id']) );
				$aux["backlinks"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_links` where `to_content_id`=?",array( $res['content_id'] ) );
				$ret[] = $aux;
			}
		}

		// If sortmode is versions, links or backlinks sort using the ad-hoc function and reduce using old_offse and old_maxRecords
		if ($old_sort_mode == 'versions_asc') {
			usort($ret, 'compare_versions');
		}

		if ($old_sort_mode == 'versions_desc') {
			usort($ret, 'r_compare_versions');
		}

		if ($old_sort_mode == 'links_desc') {
			usort($ret, 'compare_links');
		}

		if ($old_sort_mode == 'links_asc') {
			usort($ret, 'r_compare_links');
		}

		if ($old_sort_mode == 'backlinks_desc') {
			usort($ret, 'compare_backlinks');
		}

		if ($old_sort_mode == 'backlinks_asc') {
			usort($ret, 'r_compare_backlinks');
		}

		if (in_array($old_sort_mode, array(
			'versions_desc',
			'versions_asc',
			'links_asc',
			'links_desc',
			'backlinks_asc',
			'backlinks_desc'
		))) {
			$ret = array_slice($ret, $old_offset, $old_maxRecords);
		}

		$retval = array();
		$retval["data"] = $ret;
		$retval["cant"] = $num_or;
		return $retval;
	}

	function wiki_stats() {
		global $gBitSystem;
		$stats = array();
		if( $gBitSystem->isPackageActive( 'wiki' ) ) {
			require_once( WIKI_PKG_PATH.'BitPage.php' );

			$stats["pages"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_pages`",array());
			$stats["versions"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_history`",array());

			if ($stats["pages"]) {
				$stats["vpp"] = $stats["versions"] / $stats["pages"];
			} else {
				$stats["vpp"] = 0;
			}
			$stats["visits"] = $this->getOne("select sum(`hits`) from `".BIT_DB_PREFIX."tiki_content` WHERE `content_type_guid`=?",array( BITPAGE_CONTENT_TYPE_GUID ));
			$or = $this->list_orphan_pages(0, -1, 'title_desc', '');
			$stats["orphan"] = $or["cant"];
			$links = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_links`",array());

			if ($stats["pages"]) {
				$stats["lpp"] = $links / $stats["pages"];
			} else {
				$stats["lpp"] = 0;
			}
			$stats["size"] = $this->getOne("select sum(`page_size`) from `".BIT_DB_PREFIX."tiki_pages`",array());

			if ($stats["pages"]) {
				$stats["bpp"] = $stats["size"] / $stats["pages"];
			} else {
				$stats["bpp"] = 0;
			}
			$stats["size"] = $stats["size"] / 1000000;
		}
		return $stats;
	}

	function quiz_stats() {
		global $gBitSystem;
		$stats = array();
		if( $gBitSystem->isPackageActive( 'quizzes' ) ) {
			global $quizlib;
			require_once( QUIZZES_PKG_PATH.'quiz_lib.php' );
			$quizlib->compute_quiz_stats();
			$stats = array();
			$stats["quizzes"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_quizzes`",array());
			$stats["questions"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_quiz_questions`",array());
			if ($stats["quizzes"]) {
				$stats["qpq"] = $stats["questions"] / $stats["quizzes"];
			} else {
				$stats["qpq"] = 0;
			}
			$stats["visits"] = $this->getOne("select sum(`times_taken`) from `".BIT_DB_PREFIX."tiki_quiz_stats_sum`",array());
			$stats["avg"] = $this->getOne("select avg(`avgavg`) from `".BIT_DB_PREFIX."tiki_quiz_stats_sum`",array());
			$stats["avgtime"] = $this->getOne("select avg(`avgtime`) from `".BIT_DB_PREFIX."tiki_quiz_stats_sum`",array());
		}
		return $stats;
	}

	function image_gal_stats() {
		$stats = array();
		$stats["galleries"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_galleries`",array());
		$stats["images"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_images`",array());
		$stats["ipg"] = ($stats["galleries"] ? $stats["images"] / $stats["galleries"] : 0);
		$stats["size"] = $this->getOne("select sum(`filesize`) from `".BIT_DB_PREFIX."tiki_images_data` where `type`=?",array('o'));
		$stats["bpi"] = ($stats["images"] ? $stats["size"] / $stats["images"] : 0);
		$stats["size"] = $stats["size"] / 1000000;
		$stats["visits"] = $this->getOne("select sum(`hits`) from `".BIT_DB_PREFIX."tiki_galleries`",array());
		return $stats;
	}

	function file_gal_stats() {
		$stats = array();
		$stats["galleries"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_file_galleries`",array());
		$stats["files"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_files`",array());
		$stats["fpg"] = ($stats["galleries"] ? $stats["files"] / $stats["galleries"] : 0);
		$stats["size"] = $this->getOne("select sum(`filesize`) from `".BIT_DB_PREFIX."tiki_files`",array());
		$stats["size"] = $stats["size"] / 1000000;
		$stats["bpf"] = ($stats["galleries"] ? $stats["size"] / $stats["galleries"] : 0);
		$stats["visits"] = $this->getOne("select sum(`hits`) from `".BIT_DB_PREFIX."tiki_file_galleries`",array());
		$stats["downloads"] = $this->getOne("select sum(`downloads`) from `".BIT_DB_PREFIX."tiki_files`",array());
		return $stats;
	}

	function cms_stats() {
		global $gBitSystem;
		$stats = array();
		if( $gBitSystem->isPackageActive( 'articles' ) ) {
			require_once( ARTICLES_PKG_PATH.'BitArticle.php' );

			$stats["articles"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_articles`",array());
			$stats["reads"] = $this->getOne("select sum(`hits`) from `".BIT_DB_PREFIX."tiki_content` WHERE `content_type_guid`=?",array( BITARTICLE_CONTENT_TYPE_GUID ));
			$stats["rpa"] = ($stats["articles"] ? $stats["reads"] / $stats["articles"] : 0);
//			$stats["size"] = $this->getOne("select sum(`size`) from `".BIT_DB_PREFIX."tiki_articles`",array());
			$stats["bpa"] = ($stats["articles"] ? $stats["size"] / $stats["articles"] : 0);
			$stats["topics"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_article_topics` where `active`=?",array('y'));
		}
		return $stats;
	}

	function forum_stats() {
		$stats = array();
		$stats["forums"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_forums`",array());
		$stats["topics"] = $this->getOne( "select count(*) from `".BIT_DB_PREFIX."tiki_comments`,`".BIT_DB_PREFIX."tiki_forums` where `object`=".$this->sql_cast('`forum_id`','string')." and `object_type`=? and `parent_id`=?",array('forum',0));
		$stats["threads"] = $this->getOne( "select count(*) from `".BIT_DB_PREFIX."tiki_comments`,`".BIT_DB_PREFIX."tiki_forums` where `object`=".$this->sql_cast('`forum_id`','string')." and `object_type`=? and `parent_id`<>?",array('forum',0));
		$stats["tpf"] = ($stats["forums"] ? $stats["topics"] / $stats["forums"] : 0);
		$stats["tpt"] = ($stats["topics"] ? $stats["threads"] / $stats["topics"] : 0);
		$stats["visits"] = $this->getOne("select sum(`hits`) from `".BIT_DB_PREFIX."tiki_forums`",array());
		return $stats;
	}

	function blog_stats() {
		global $gBitSystem;
		$stats = array();
		if( $gBitSystem->isPackageActive( 'blogs' ) ) {
			require_once( BLOGS_PKG_PATH.'BitBlogPost.php' );
			$stats["blogs"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_blogs`",array());
			$stats["posts"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_blog_posts`",array());
			$stats["ppb"] = ($stats["blogs"] ? $stats["posts"] / $stats["blogs"] : 0);
	//		$stats["size"] = $this->getOne("select sum(`data_size`) from `".BIT_DB_PREFIX."tiki_blog_posts`",array());
	//		$stats["bpp"] = ($stats["posts"] ? $stats["size"] / $stats["posts"] : 0);
			$stats["visits"] = $this->getOne("select sum(`hits`) from `".BIT_DB_PREFIX."tiki_content` WHERE `content_type_guid`=?",array( BITBLOGPOST_CONTENT_TYPE_GUID ));
		}
		return $stats;
	}

	function poll_stats() {
		$stats = array();
		$stats["polls"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_polls`",array());
		$stats["votes"] = $this->getOne("select sum(`votes`) from `".BIT_DB_PREFIX."tiki_poll_options`",array());
		$stats["vpp"] = ($stats["polls"] ? $stats["votes"] / $stats["polls"] : 0);
		return $stats;
	}

	function faq_stats() {
		$stats = array();
		$stats["faqs"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_faqs`",array());
		$stats["questions"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_faq_questions`",array());
		$stats["qpf"] = ($stats["faqs"] ? $stats["questions"] / $stats["faqs"] : 0);
		return $stats;
	}

	function registrationStats( $pPeriod ) {
		if( !is_numeric( $pPeriod ) || empty( $pPeriod ) ) {
			$pPeriod = 86400;
		}
		$query = "SELECT ((`registration_date` / ?) * ?) AS day, COUNT(`user_id`) FROM `".BIT_DB_PREFIX."users_users`
				  GROUP BY( `registration_date` / ? ) ORDER BY COUNT(`user_id`) DESC";
		$stats['per_period'] = $this->GetAssoc( $query, array( $pPeriod, $pPeriod, $pPeriod ) );
		$stats['max'] = !empty( $stats['per_period'] ) ? current( $stats['per_period'] ) : 0;
		krsort( $stats['per_period'] );
		return $stats;
	}

	function user_stats() {
		$stats = array();
		$stats["users"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."users_users`",array());
		$stats["bookmarks"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_user_bookmarks_urls`",array());
		$stats["bpu"] = ($stats["users"] ? $stats["bookmarks"] / $stats["users"] : 0);
		return $stats;
	}

	function site_stats() {
		$stats = array();
		$stats["started"] = $this->getOne("select min(`day`) from `".BIT_DB_PREFIX."tiki_pageviews`",array());
		$stats["days"] = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_pageviews`",array());
		$stats["pageviews"] = $this->getOne("select sum(`pageviews`) from `".BIT_DB_PREFIX."tiki_pageviews`");
		$stats["ppd"] = ($stats["days"] ? $stats["pageviews"] / $stats["days"] : 0);
		$stats["bestpvs"] = $this->getOne("select max(`pageviews`) from `".BIT_DB_PREFIX."tiki_pageviews`",array());
		$stats["bestday"] = $this->getOne("select `day` from `".BIT_DB_PREFIX."tiki_pageviews` where `pageviews`=?",array((int)$stats["bestpvs"]));
		$stats["worstpvs"] = $this->getOne("select min(`pageviews`) from `".BIT_DB_PREFIX."tiki_pageviews`",array());
		$stats["worstday"] = $this->getOne("select `day` from `".BIT_DB_PREFIX."tiki_pageviews` where `pageviews`=?",array((int)$stats["worstpvs"]));
		return $stats;
	}

	// Stats ////
	/*shared*/
	function add_pageview() {
		$dayzero = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
		$cant = $this->getOne("select count(*) from `".BIT_DB_PREFIX."tiki_pageviews` where `day`=?",array((int)$dayzero));

		if ($cant) {
		$query = "update `".BIT_DB_PREFIX."tiki_pageviews` set `pageviews`=`pageviews`+1 where `day`=?";
		} else {
		$query = "insert into `".BIT_DB_PREFIX."tiki_pageviews`(`day`,`pageviews`) values(?,1)";
		}
		$result = $this->query($query,array((int)$dayzero));
	}


	function get_usage_chart_data() {
		global $gBitSystem;
		if( $gBitSystem->isPackageActive( 'wiki' ) ) {
			$data[] = array( WIKI_PKG_NAME,   $this->getOne("select sum(`hits`) from `".BIT_DB_PREFIX."tiki_content` WHERE content_type_guid=?",array( BITPAGE_CONTENT_TYPE_GUID )));
		}
		if( $gBitSystem->isPackageActive( 'blogs' ) ) {
			require_once( BLOGS_PKG_PATH.'BitBlogPost.php' );
			$postHits = $this->getOne("select sum(`hits`) from `".BIT_DB_PREFIX."tiki_content` WHERE content_type_guid=?",array( BITBLOGPOST_CONTENT_TYPE_GUID ) );
			$blogHits = $this->getOne("select sum(`hits`) from `".BIT_DB_PREFIX."tiki_blogs`" );
			$data[] = array( "blogs", $blogHits + $postHits );
		}
/*
not liberated
		if( $gBitSystem->isPackageActive( 'imagegals' ) ) {
			$data[] = array( IMAGEGALS_PKG_NAME,  $this->getOne("select sum(`hits`) from `".BIT_DB_PREFIX."tiki_galleries`",array()));
		}
		if( $gBitSystem->isPackageActive( 'filegals' ) ) {
			$data[] = array( FILEGALS_PKG_NAME, $this->getOne("select sum(`hits`) from `".BIT_DB_PREFIX."tiki_file_galleries`",array()));
		}
		if( $gBitSystem->isPackageActive( 'fags' ) ) {
			$data[] = array( FAQS_PACKAGE_NAME,   $this->getOne("select sum(`hits`) from `".BIT_DB_PREFIX."tiki_faqs`",array()));
		}
		if( $gBitSystem->isPackageActive( 'quizzes' ) ) {
			global $quizlib;
			$quizlib->compute_quiz_stats();
			$data[] = array( QUIZZES_PKG_NAME,$this->getOne("select sum(`times_taken`) from `".BIT_DB_PREFIX."tiki_quiz_stats_sum`",array()));
		}
		if( $gBitSystem->isPackageActive( 'articles' ) ) {
			$data[] = array( ARTICLES_PKG_NAME,   $this->getOne("select sum(`reads`) from `".BIT_DB_PREFIX."tiki_articles`",array()));
		}
		if( $gBitSystem->isPackageActive( 'bitforums' ) ) {
			$data[] = array( BITFORUMS_PKG_NAME, $this->getOne("select sum(`hits`) from `".BIT_DB_PREFIX."tiki_forums`",array()));
		}
		if( $gBitSystem->isPackageActive( 'games' ) ) {
			$data[] = array( "games",  $this->getOne("select sum(`hits`) from `".BIT_DB_PREFIX."tiki_games`",array()));
		}
*/
		return $data;
	}



}

global $statslib;
$statslib = new StatsLib();

?>
