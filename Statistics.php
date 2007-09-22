<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/Statistics.php,v 1.3 2007/09/22 12:10:32 threna Exp $
 *
 * $Id: Statistics.php,v 1.3 2007/09/22 12:10:32 threna Exp $
 * @package stats
 */

/**
 * @package stats
 * @subpackage Stats
 */
class Statistics extends BitBase {

	/**
	 * Initiate class
	 */
	function Statistics() {
		BitBase::BitBase();
	}

	/**
	 * getRefererList gets a list of referers
	 *
	 * @param array $pListHash
	 * @access public
	 * @return array of referers
	 */
	function getRefererList( &$pListHash ) {
		if( empty( $pListHash['sort_mode'] )) {
			$pListHash['sort_mode'] = 'hits_desc';
		}

		LibertyContent::prepGetList( $pListHash );

		// LibertyContent::prepGetList assumes that 'hits_' refers to liberty_content what is wrong in this case
		if ( is_string( $pListHash['sort_mode'] ) && strpos( $pListHash['sort_mode'], 'lch.' ) === 0 ) {
			$pListHash['sort_mode'] = substr($pListHash['sort_mode'],4);
		}

		$ret = $bindVars = array();
		$selectSql = $joinSql = $whereSql = "";

		if( !empty( $pListHash['find'] ) && is_string( $pListHash['find'] )) {
			$whereSql  .= empty( $whereSql ) ? ' WHERE ' : ' AND ';
			$whereSql  .= " UPPER( `referer` ) LIKE ?";
			$bindVars[] = '%'.strtoupper( $pListHash['find'] ).'%';
		}

		$query = "SELECT * FROM `".BIT_DB_PREFIX."stats_referers` $whereSql ORDER BY ".$this->mDb->convertSortmode( $pListHash['sort_mode'] );;
		$ret = $this->mDb->getAll( $query, $bindVars, $pListHash['max_records'], $pListHash['offset'] );

		$query = "SELECT COUNT(*) FROM `".BIT_DB_PREFIX."stats_referers` $whereSql";
		$pListHash['cant'] = $this->mDb->getOne( $query, $bindVars);
		LibertyContent::postGetList( $pListHash );

		return $ret;
	}

	/**
	 * expungeReferers will remove all referers unconditionally
	 *
	 * @access public
	 * @return TRUE on success, FALSE on failure
	 */
	function expungeReferers() {
		return( $this->mDb->query( "DELETE FROM `".BIT_DB_PREFIX."stats_referers`" ));
	}

	/**
	 * storeReferer will insert new record in referer table
	 *
	 * @access public
	 * @return TRUE on success, FALSE on failure - mErrors will contain reason for failure
	 */
	function storeReferer() {
		global $gBitSystem;

		if( !empty( $_SERVER['HTTP_REFERER'] ) && $parsed = parse_url( $_SERVER['HTTP_REFERER'] )) {
			if( !empty( $parsed["host"] ) && !strstr( $_SERVER["HTTP_HOST"], $parsed["host"] )) {
				$now = $gBitSystem->getUTCTime();

				$store = $parsed['scheme'].'://'.$parsed['host'];

				$query = "UPDATE `".BIT_DB_PREFIX."stats_referers` SET `hits`=`hits`+1,`last`=? WHERE `referer`=?";
				$this->mDb->query( $query, array( $now, $store ));

				// if the above didn't affect the db, we know that the entry doesn't exist yet.
				if( !$this->mDb->mDb->Affected_Rows() ) {
					$query = "INSERT INTO `".BIT_DB_PREFIX."stats_referers`( `last`, `referer`, `hits` ) VALUES( ?, ?, ? )";
					$this->mDb->query( $query, array( $now, $store, 1 ));
				}
			}
		}
		return TRUE;
	}

	/**
	 * addPageview to the pageview count
	 *
	 * @access public
	 * @return void
	 */
	function addPageview() {
		$dayzero = mktime( 0, 0, 0, date( "m" ), date( "d" ), date( "Y" ));
		$query = "UPDATE `".BIT_DB_PREFIX."stats_pageviews` SET `pageviews`=`pageviews`+1 WHERE `stats_day`=?";
		$this->mDb->query( $query, array( $dayzero ));

		// if the above didn't affect the db, we know that the entry doesn't exist yet.
		if( !$this->mDb->mDb->Affected_Rows() ) {
			$query = "INSERT INTO `".BIT_DB_PREFIX."stats_pageviews`( `pageviews`, `stats_day` ) VALUES( ?, ? )";
			$this->mDb->query( $query, array( 1, $dayzero ));
		}
	}

	/**
	 * registrationStats
	 *
	 * @param array $pPeriod
	 * @access public
	 * @return TRUE on success, FALSE on failure - mErrors will contain reason for failure
	 */
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
				$format = 'Y \Week W';
				break;
			case 'month':
			default:
				$format = 'Y-m';
				break;
		}

		$sqlPeriod = $this->mDb->SQLDate( $format, $this->mDb->SQLIntToTimestamp( 'registration_date' ));
		$query = "
			SELECT $sqlPeriod AS period, COUNT(`user_id`)
			FROM `".BIT_DB_PREFIX."users_users`
			GROUP BY( $sqlPeriod )
			ORDER BY COUNT(`user_id`) DESC";
		$ret['per_period'] = $this->mDb->getAssoc( $query );
		$ret['max'] = !empty( $ret['per_period'] ) ? current( $ret['per_period'] ) : 0;
		krsort( $ret['per_period'] );
		return $ret;
	}

	/**
	 * getSiteStats will get a brief overview over the site
	 *
	 * @access public
	 * @return array with site stats
	 */
	function getSiteStats() {
		$ret["started"]   = $this->mDb->getOne( "SELECT MIN(`stats_day`) FROM `".BIT_DB_PREFIX."stats_pageviews`" );
		$ret["days"]      = $this->mDb->getOne( "SELECT COUNT(*) FROM `".BIT_DB_PREFIX."stats_pageviews`" );
		$ret["pageviews"] = $this->mDb->getOne( "SELECT SUM(`pageviews`) FROM `".BIT_DB_PREFIX."stats_pageviews`" );
		$ret["ppd"]       = ( $ret["days"] ? $ret["pageviews"] / $ret["days"] : 0 );
		$ret["bestpvs"]   = $this->mDb->getOne( "SELECT MAX(`pageviews`) FROM `".BIT_DB_PREFIX."stats_pageviews`" );
		$ret["bestday"]   = $this->mDb->getOne( "SELECT `stats_day` FROM `".BIT_DB_PREFIX."stats_pageviews` WHERE `pageviews`=?",array( (int)$ret["bestpvs"] ));
		$ret["worstpvs"]  = $this->mDb->getOne( "SELECT MIN(`pageviews`) FROM `".BIT_DB_PREFIX."stats_pageviews`" );
		$ret["worstday"]  = $this->mDb->getOne( "SELECT `stats_day` FROM `".BIT_DB_PREFIX."stats_pageviews` WHERE `pageviews`=?",array( (int)$ret["worstpvs"] ));
		return $ret;
	}

	/**
	 * getContentOverview will get a simple overview based on stats available available in liberty
	 *
	 * @param array $pParamHash
	 * @access public
	 * @return array with content type stats
	 */
	function getContentOverview( $pParamHash = NULL ) {
		global $gLibertySystem;

		if( empty( $pParamHash['sort_mode'] )) {
			$pParamHash['sort_mode'] = 'content_count_desc';
		}

		$query = "
			SELECT lc.`content_type_guid` AS `hash_key`, COUNT( lc.`content_id` ) AS `content_count`, SUM( lch.`hits` ) AS `total_hits`
			FROM `".BIT_DB_PREFIX."liberty_content` lc
			LEFT OUTER JOIN `".BIT_DB_PREFIX."liberty_content_hits` lch ON( lc.`content_id` = lch.`content_id` )
			GROUP BY lc.`content_type_guid` ORDER BY ".$this->mDb->convertSortmode( $pParamHash['sort_mode'] );
		$ret = $this->mDb->getAssoc( $query );

		return $ret;
	}

	/**
	 * getContentStats will check all available content for the method Object::getStats() and will call and include the data
	 *
	 * @access public
	 * @return array with content-specific stats
	 */
	function getContentStats() {
		global $gLibertySystem;

		$ret = array();
		foreach( $gLibertySystem->mContentTypes as $guid => $type ) {
			if( $gLibertySystem->requireHandlerFile( $type )) {
				$object = new $type['handler_class']();
				if( method_exists( $object, 'getStats' )) {
					$ret[$guid] = $object->getStats();
				}
			}
		}

		return $ret;
	}

	/**
	 * getPageviewChartData will fetch all data needed to create a graph with PHPlot
	 *
	 * @param numeric $pDays Number of days we will use to create a graph
	 * @access public
	 * @return array for PHPlot graph
	 */
	function getPageviewChartData( $pDays = 7 ) {
		$now = mktime( 0, 0, 0, date( "m" ), date( "d" ), date( "Y" ));
		$dfrom = 0;
		if( $pDays != 0 ) $dfrom = $now - ( $pDays * 24 * 60 * 60 );

		$query = "SELECT `stats_day`, `pageviews` FROM `".BIT_DB_PREFIX."stats_pageviews` WHERE `stats_day`<=? AND `stats_day`>=? ORDER BY `stats_day` ASC";
		$result = $this->mDb->query( $query,array( ( int )$now, ( int )$dfrom ));
		$ret = array();
		$n = ceil( $result->numRows() / 20 );
		$i = 0;

		while( $res = $result->fetchRow() ) {
			if( $i % $n == 0 ) {
				$data = array(
					date( "j M Y", $res["stats_day"] ),
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

	/**
	 * getUsageChartData will fetch all data needed to create a graph with PHPlot
	 *
	 * @access public
	 * @return array for PHPlot graph
	 */
	function getUsageChartData() {
		global $gBitSystem, $gLibertySystem;
		$ret['data'][0][] = 'a';
		foreach( $gLibertySystem->mContentTypes as $contentType ) {
			if( $gBitSystem->isPackageActive( $contentType['handler_package'] )) {
				$hits = $this->mDb->getOne( "
					SELECT SUM(`hits`)
					FROM `".BIT_DB_PREFIX."liberty_content` lc
						LEFT OUTER JOIN `".BIT_DB_PREFIX."liberty_content_hits` liberty_content_hits
							ON (lc.`content_id` = liberty_content_hits.`content_id`)
					WHERE content_type_guid=?", array( $contentType['content_type_guid'] )
				);
				if( !empty( $hits )) {
					$ret['legend'][] = tra( $contentType['content_description'] );
					$ret['data'][0][] = $hits;
				}
			}
		}
		return $ret;
	}

	/**
	 * getContentTypeChartData will fetch all data needed to create a graph with PHPlot
	 *
	 * @param array $pContentTypeGuid specify the content_type_guid you want to create a graph for
	 * @access public
	 * @return array for PHPlot graph
	 */
	function getContentTypeChartData( $pContentTypeGuid=NULL ) {
		global $gLibertySystem;
		$ret['data'] = array();

		if( in_array( $pContentTypeGuid, array_keys( $gLibertySystem->mContentTypes ))) {
			$query = "
				SELECT `hits`, `title`, `content_type_guid`
				FROM `".BIT_DB_PREFIX."liberty_content` lc
					LEFT OUTER JOIN `".BIT_DB_PREFIX."liberty_content_hits` liberty_content_hits
						ON (lc.`content_id` = liberty_content_hits.`content_id`)
				WHERE `content_type_guid`=?
				ORDER BY `hits` DESC";
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
				$query = "
					SELECT `hits`, `title`, `content_type_guid`
					FROM `".BIT_DB_PREFIX."liberty_content` lc
					LEFT OUTER JOIN `".BIT_DB_PREFIX."liberty_content_hits` liberty_content_hits
					ON (lc.`content_id` = liberty_content_hits.`content_id`)
					WHERE `content_type_guid`=?
					ORDER BY `hits` DESC";
				$result = $this->mDb->query( $query, array( $contentType['content_type_guid'] ), 40 );
				// this is needed to ensure all arrays have same size
				$ret['data'][$contentType['content_type_guid']] = array_fill( 0, 40, array( NULL, NULL ));
				$i = 0;
				while( $res = $result->fetchRow() ) {
					$ret['data'][$contentType['content_type_guid']][$i++] = array(
						$res['title'],
						$res['hits'],
					);
				}
			}
			$ret['title'] = 'All Content';
		}
		$ret['max'] = $this->mDb->getOne( "
			SELECT MAX(`hits`)
			FROM `".BIT_DB_PREFIX."liberty_content` lc
				LEFT OUTER JOIN `".BIT_DB_PREFIX."liberty_content_hits` liberty_content_hits
					ON (lc.`content_id` = liberty_content_hits.`content_id`)
		");
		return $ret;
	}
}
?>
