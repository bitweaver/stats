<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/Attic/stats_lib.php,v 1.16 2006/02/02 09:14:14 squareing Exp $
 *
 * Copyright (c) 2004 bitweaver.org
 * Copyright (c) 2003 tikwiki.org
 * Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See copyright.txt for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details
 *
 * $Id: stats_lib.php,v 1.16 2006/02/02 09:14:14 squareing Exp $
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
		$query = "delete from stats_referers";

		$result = $this->mDb->query($query);
	}

	function list_referer_stats($offset, $maxRecords, $sort_mode, $find) {
		$bindvars = array();
		if ($find) {
			$findesc = $this->mDb->qstr('%' . strtoupper( $find ) . '%');
			$mid = " where (UPPER(`referer`) like ?)";
			$bindvars = array($findesc);
		} else {
			$mid = "";
		}

		$query = "select * from `".BIT_DB_PREFIX."stats_referers` $mid order by ".$this->mDb->convert_sortmode($sort_mode);;
		$query_cant = "select count(*) from `".BIT_DB_PREFIX."stats_referers` $mid";
		$result = $this->mDb->query($query,$bindvars,$maxRecords,$offset);
		$cant = $this->mDb->getOne($query_cant,$bindvars);
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
			global $gBitSystem;
			$now = $gBitSystem->getUTCTime();
			$cant = $this->mDb->getOne("select count(*) from `".BIT_DB_PREFIX."stats_referers` where `referer`=?",array($referer));

			$query = "update `".BIT_DB_PREFIX."stats_referers` set `hits`=`hits`+1,`last`=? where `referer`=?";
			$rs = $this->mDb->query($query,array((int)$now,$referer));
			if( !$this->mDb->mDb->Affected_Rows() ) {
				$query = "insert into `".BIT_DB_PREFIX."stats_referers`(`last`,`referer`,`hits`) values(?,?,1)";
				$result = $this->mDb->query($query,array((int)$now,$referer));
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
		$query = "select * from `".BIT_DB_PREFIX."wiki_pages` tp INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON( lc.`content_id`=tp.`content_id` ) $mid order by ".$this->mDb->convert_sortmode($sort_mode);
		$query_cant = "select count(*) from `".BIT_DB_PREFIX."wiki_pages` $mid";
		$result = $this->mDb->query($query,$bindvars,-1,0);
		$cant = $this->mDb->getOne($query_cant,$bindvars);
		$ret = array();
		$num_or = 0;

		while ($res = $result->fetchRow()) {
			$title = $res["title"];
			$queryc = "select count(*) from `".BIT_DB_PREFIX."liberty_content_links` where `to_content_id`=?";
			$cant = $this->mDb->getOne( $queryc,array($res['content_id'] ) );
			$queryc = "select count(*) from `".BIT_DB_PREFIX."liberty_structures` ls WHERE ls.`content_id`=?";
			$cant += $this->mDb->getOne( $queryc, array( $res['content_id'] ) );

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
				$aux["versions"] = $this->mDb->getOne("select count(*) from `".BIT_DB_PREFIX."liberty_content_history` WHERE `page_id`=?",array($res['page_id']));
				$aux["links"] = $this->mDb->getOne("select count(*) from `".BIT_DB_PREFIX."liberty_content_links` WHERE `from_content_id`=?",array( $res['content_id']) );
				$aux["backlinks"] = $this->mDb->getOne("select count(*) from `".BIT_DB_PREFIX."liberty_content_links` where `to_content_id`=?",array( $res['content_id'] ) );
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

			$stats["pages"] = $this->mDb->getOne("select count(*) from `".BIT_DB_PREFIX."wiki_pages`",array());
			$stats["versions"] = $this->mDb->getOne("select count(*) from `".BIT_DB_PREFIX."liberty_content_history`",array());

			if ($stats["pages"]) {
				$stats["vpp"] = $stats["versions"] / $stats["pages"];
			} else {
				$stats["vpp"] = 0;
			}
			$stats["visits"] = $this->mDb->getOne("select sum(`hits`) from `".BIT_DB_PREFIX."liberty_content` WHERE `content_type_guid`=?",array( BITPAGE_CONTENT_TYPE_GUID ));
			$or = $this->list_orphan_pages(0, -1, 'title_desc', '');
			$stats["orphan"] = $or["cant"];
			$links = $this->mDb->getOne("select count(*) from `".BIT_DB_PREFIX."liberty_content_links`",array());

			if ($stats["pages"]) {
				$stats["lpp"] = $links / $stats["pages"];
			} else {
				$stats["lpp"] = 0;
			}
			$stats["size"] = $this->mDb->getOne("select sum(`page_size`) from `".BIT_DB_PREFIX."wiki_pages`",array());

			if ($stats["pages"]) {
				$stats["bpp"] = $stats["size"] / $stats["pages"];
			} else {
				$stats["bpp"] = 0;
			}
			$stats["size"] = $stats["size"] / 1000000;
		}
		return $stats;
	}

	function cms_stats() {
		global $gBitSystem;
		$stats = array();
		if( $gBitSystem->isPackageActive( 'articles' ) ) {
			require_once( ARTICLES_PKG_PATH.'BitArticle.php' );

			$stats["articles"] = $this->mDb->getOne("select count(*) from `".BIT_DB_PREFIX."articles`",array());
			$stats["reads"] = $this->mDb->getOne("select sum(`hits`) from `".BIT_DB_PREFIX."liberty_content` WHERE `content_type_guid`=?",array( BITARTICLE_CONTENT_TYPE_GUID ));
			$stats["rpa"] = ($stats["articles"] ? $stats["reads"] / $stats["articles"] : 0);
//			$stats["size"] = $this->mDb->getOne("select sum(`size`) from `".BIT_DB_PREFIX."articles`",array());
//			$stats["bpa"] = ($stats["articles"] ? $stats["size"] / $stats["articles"] : 0);
			$stats["topics"] = $this->mDb->getOne("select count(*) from `".BIT_DB_PREFIX."article_topics` where `active`=?",array('y'));
		}
		return $stats;
	}

	function blog_stats() {
		global $gBitSystem;
		$stats = array();
		if( $gBitSystem->isPackageActive( 'blogs' ) ) {
			require_once( BLOGS_PKG_PATH.'BitBlogPost.php' );
			$stats["blogs"] = $this->mDb->getOne("select count(*) from `".BIT_DB_PREFIX."blogs`",array());
			$stats["posts"] = $this->mDb->getOne("select count(*) from `".BIT_DB_PREFIX."blog_posts`",array());
			$stats["ppb"] = ($stats["blogs"] ? $stats["posts"] / $stats["blogs"] : 0);
	//		$stats["size"] = $this->mDb->getOne("select sum(`data_size`) from `".BIT_DB_PREFIX."blog_posts`",array());
	//		$stats["bpp"] = ($stats["posts"] ? $stats["size"] / $stats["posts"] : 0);
			$stats["visits"] = $this->mDb->getOne("select sum(`hits`) from `".BIT_DB_PREFIX."liberty_content` WHERE `content_type_guid`=?",array( BITBLOGPOST_CONTENT_TYPE_GUID ));
		}
		return $stats;
	}

	function registrationStats( $pPeriod ) {
		global $gBitDbType;

		switch( $pPeriod ) {
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
				$format = 'Y-WW';
				break;
			case 'month':
			default:
				$format = 'Y-m';
				break;
		}

		$sqlPeriod = $this->mDb->SQLDate( $format, $this->mDb->SQLIntToTimestamp( '`registration_date`' ) );
		$query = "SELECT $sqlPeriod AS period, COUNT(`user_id`) FROM `".BIT_DB_PREFIX."users_users`
					GROUP BY( $sqlPeriod ) ORDER BY COUNT(`user_id`) DESC";
		$stats['per_period'] = $this->mDb->getAssoc( $query );
		$stats['max'] = !empty( $stats['per_period'] ) ? current( $stats['per_period'] ) : 0;
		krsort( $stats['per_period'] );
		return $stats;
	}

	function user_stats() {
		$stats = array();
		$stats["users"] = $this->mDb->getOne("select count(*) from `".BIT_DB_PREFIX."users_users`",array());
		$stats["bookmarks"] = $this->mDb->getOne("select count(*) from `".BIT_DB_PREFIX."tidbits_user_bookmarks_urls`",array());
		$stats["bpu"] = ($stats["users"] ? $stats["bookmarks"] / $stats["users"] : 0);
		return $stats;
	}

	function site_stats() {
		$stats = array();
		$stats["started"] = $this->mDb->getOne("select min(`day`) from `".BIT_DB_PREFIX."stats_pageviews`",array());
		$stats["days"] = $this->mDb->getOne("select count(*) from `".BIT_DB_PREFIX."stats_pageviews`",array());
		$stats["pageviews"] = $this->mDb->getOne("select sum(`pageviews`) from `".BIT_DB_PREFIX."stats_pageviews`");
		$stats["ppd"] = ($stats["days"] ? $stats["pageviews"] / $stats["days"] : 0);
		$stats["bestpvs"] = $this->mDb->getOne("select max(`pageviews`) from `".BIT_DB_PREFIX."stats_pageviews`",array());
		$stats["bestday"] = $this->mDb->getOne("select `day` from `".BIT_DB_PREFIX."stats_pageviews` where `pageviews`=?",array((int)$stats["bestpvs"]));
		$stats["worstpvs"] = $this->mDb->getOne("select min(`pageviews`) from `".BIT_DB_PREFIX."stats_pageviews`",array());
		$stats["worstday"] = $this->mDb->getOne("select `day` from `".BIT_DB_PREFIX."stats_pageviews` where `pageviews`=?",array((int)$stats["worstpvs"]));
		return $stats;
	}

	// Stats ////
	/*shared*/
	function add_pageview() {
		$dayzero = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
		$this->mDb->StartTrans();
		$cant = $this->mDb->getOne("select count(*) from `".BIT_DB_PREFIX."stats_pageviews` where `day`=?",array((int)$dayzero));

		if ($cant) {
		$query = "update `".BIT_DB_PREFIX."stats_pageviews` set `pageviews`=`pageviews`+1 where `day`=?";
		} else {
		$query = "insert into `".BIT_DB_PREFIX."stats_pageviews`(`day`,`pageviews`) values(?,1)";
		}
		$result = $this->mDb->query($query,array((int)$dayzero));
		$this->mDb->CompleteTrans();
	}

	function get_pv_chart_data( $days ) {
		$now = mktime( 0, 0, 0, date( "m" ), date( "d" ), date( "Y" ) );
		$dfrom = 0;
		if( $days != 0 ) $dfrom = $now - ( $days * 24 * 60 * 60 );

		$query = "SELECT `day`, `pageviews` FROM `".BIT_DB_PREFIX."stats_pageviews` WHERE `day`<=? AND `day`>=? ORDER BY `day` ASC";
		$result = $this->mDb->query( $query,array( ( int )$now,( int )$dfrom ) );
		$ret = array();
		$n = ceil( $result->numRows() / 20 );
		$i = 0;

		while( $res = $result->fetchRow() ) {
			if( $i % $n == 0 ) {
				$data = array(
					date( "j M Y", $res["day"] ),
					$res["pageviews"]
				);
			} else {
				$data = array(
					"",
					$res["pageviews"]
				);
			}
			$ret[] = $data;
			$i++;
		}

		return $ret;
	}

	function get_usage_chart_data() {
		global $gBitSystem, $gLibertySystem;
		$ret['data'][0][] = 'a';
		foreach( $gLibertySystem->mContentTypes as $contentType ) {
			if( $gBitSystem->isPackageActive( $contentType['handler_package'] ) ) {
				$hits = $this->mDb->getOne( "SELECT SUM(`hits`) FROM `".BIT_DB_PREFIX."liberty_content` WHERE content_type_guid=?", array( $contentType['content_type_guid'] ) );
				if( !empty( $hits ) ) {
					$ret['legend'][] = tra( $contentType['content_description'] );
					$ret['data'][0][] = $hits;
				}
			}
		}
		return $ret;
	}

	function get_item_chart_data( $pContentTypeGuid=NULL ) {
		global $gBitSystem, $gLibertySystem;
		$ret['data'] = array();

		if( in_array( $pContentTypeGuid, array_keys( $gLibertySystem->mContentTypes ) ) ) {
			$query = "SELECT `hits`, `title`, `content_type_guid` FROM `".BIT_DB_PREFIX."liberty_content` WHERE `content_type_guid`=? ORDER BY `hits` DESC";
			$result = $this->mDb->query( $query, array( $pContentTypeGuid ), 159 );
			// this is needed to ensure all arrays have same size
			$tmpHash[] = array( NULL, 0 );
			while( $res = $result->fetchRow() ) {
				$tmpHash[] = array(
					$res['title'],
					$res['hits'],
				);
			}
			$ret['data'][$pContentTypeGuid] = array_chunk( $tmpHash, 40 );
			$ret['title'] = $gLibertySystem->mContentTypes[$pContentTypeGuid]['content_description'];
		} else {
			foreach( $gLibertySystem->mContentTypes as $contentType ) {
				if( $gBitSystem->isPackageActive( $contentType['handler_package'] ) ) {
					$query = "SELECT `hits`, `title`, `content_type_guid` FROM `".BIT_DB_PREFIX."liberty_content` WHERE `content_type_guid`=? ORDER BY `hits` DESC";
					$result = $this->mDb->query( $query, array( $contentType['content_type_guid'] ), 40 );
					// this is needed to ensure all arrays have same size
					$ret['data'][$contentType['content_type_guid']] = array_fill( 0, 40, array( NULL, NULL ) );
					$i = 0;
					while( $res = $result->fetchRow() ) {
						$ret['data'][$contentType['content_type_guid']][$i++] = array(
							$res['title'],
							$res['hits'],
						);
					}
				}
			}
			$ret['title'] = 'All Content';
		}
		$ret['max'] = $this->mDb->getOne( "SELECT MAX(`hits`) FROM `".BIT_DB_PREFIX."liberty_content`" );
		return $ret;
	}
}

global $statslib;
$statslib = new StatsLib();

?>
