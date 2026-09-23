<?php
/**
 * $Header$
 *
 * $Id$
 * @package stats
 */

/**
 * @package stats
 * @subpackage Stats
 */
class Statistics extends BitBase {

	public static function prepGetList( &$pListHash ) {

		if( !empty( $pListHash['period'] ) ) {
			$pListHash['period_format'] = BitDb::getPeriodFormat( $pListHash['period'] );
		}

		parent::prepGetList( $pListHash );
	}

	/**
	 * getRefererList gets a list of referers
	 *
	 * @param array $pListHash
	 * @access public
	 * @return array of referers
	 */
	function getRefererList( &$pListHash ) {
		global $gBitSystem;
		$hashKey = '';

		$ret = $bindVars = array();
		$selectSql = $joinSql = $whereSql = $groupSql = "";

		$hashSql = "uu.`user_id` AS `hash_key`, ";

		if( empty( $pListHash['sort_mode'] )) {
			$pListHash['sort_mode'] = 'uu.`registration_date_desc`';
		}

		static::prepGetList( $pListHash );

		if( empty( $pListHash['period_format'] ) ) {
			$pListHash['period_format'] = 'Y-W';
		}

		if( !empty( $pListHash['period_format'] ) && !empty( $pListHash['timeframe'] ) ) {
			$whereSql .= empty( $whereSql ) ? ' WHERE ' : ' AND ';
			$whereSql .= $this->mDb->SQLDate( $pListHash['period_format'], $this->mDb->SQLIntToTimestamp( 'registration_date' )).'=?';
			$bindVars[] = $pListHash['timeframe'];
			$hashKey = 'host';
		} else {
			$hashSql = $this->mDb->SQLDate( $pListHash['period_format'], $this->mDb->SQLIntToTimestamp( 'registration_date' )).' AS `hash_key`,';
			$hashKey = 'period';
		}

		if( !empty( $pListHash['find'] ) && is_string( $pListHash['find'] )) {
			$whereSql  .= empty( $whereSql ) ? ' WHERE ' : ' AND ';
			$whereSql  .= " UPPER( `referer_url` ) LIKE ?";
			$bindVars[] = '%'.strtoupper( $pListHash['find'] ).'%';
			if( empty( $pListHash['timeframe'] ) && !empty( $pListHash['period_format'] ) ) {
				$hashSql = $this->mDb->SQLDate( $pListHash['period_format'], $this->mDb->SQLIntToTimestamp( 'registration_date' )).' AS `hash_key`,';
				$hashKey = 'period';
			}
		}

		$query = "SELECT $hashSql uu.*, sru.`referer_url`, slu.`landing_url`, slu.`landing_query`
					FROM `".BIT_DB_PREFIX."users_users` uu
					 	LEFT JOIN `".BIT_DB_PREFIX."stats_referer_users_map` srum ON(uu.`user_id`=srum.`user_id`)
						LEFT JOIN  `".BIT_DB_PREFIX."stats_referer_urls` sru ON (sru.`referer_url_id`=srum.`referer_url_id`)
						LEFT JOIN  `".BIT_DB_PREFIX."stats_landing_urls` slu ON (slu.`landing_url_id`=srum.`landing_url_id`)
				$whereSql ORDER BY ".$this->mDb->convertSortmode( $pListHash['sort_mode'] );
		if( $rs = $this->mDb->query( $query, $bindVars, -1, $pListHash['offset'], ($gBitSystem->isLive() ? 3600 : BIT_QUERY_DEFAULT) ) ) {

			while( $row = $rs->fetchRow() ) {
				$key = $row['hash_key'];
				if( $hashKey == 'host' ) {
					$key = static::refererHostKey( $row );
				}
				$ret[$key][$row['user_id']] = $row;
			}
		}

		LibertyContent::postGetList( $pListHash );

		if( $hashKey == 'host' ) {
			uasort( $ret, array( $this, 'sortRefererHash' ) );
		}

		return $ret;
	}

	public static function ownHosts() {
		static $hosts = null;
		if( $hosts !== null ) {
			return $hosts;
		}
		$raw = array();
		if( !empty( $_SERVER['HTTP_HOST'] ) ) {
			$raw[] = $_SERVER['HTTP_HOST'];
		}
		foreach( array( 'BIT_BASE_URI', 'BIT_ROOT_URI' ) as $c ) {
			if( defined( $c ) ) {
				$h = parse_url( constant( $c ), PHP_URL_HOST );
				if( !empty( $h ) ) {
					$raw[] = $h;
				}
			}
		}
		global $gBitSystem;
		if( is_object( $gBitSystem ) ) {
			$ks = $gBitSystem->getConfig( 'kernel_server_name' );
			if( !empty( $ks ) ) {
				$raw[] = $ks;
			}
		}
		$set = array();
		foreach( $raw as $h ) {
			$h = strtolower( preg_replace( '/:\d+$/', '', $h ) );
			$h = preg_replace( '/^www\./', '', $h );
			if( $h !== '' ) {
				$set[$h] = true;
			}
		}
		$hosts = array_keys( $set );
		return $hosts;
	}

	public static function isOwnHost( $pHost ) {
		$h = strtolower( preg_replace( '/:\d+$/', '', (string)$pHost ) );
		$h = preg_replace( '/^www\./', '', $h );
		if( $h === '' ) {
			return false;
		}
		foreach( static::ownHosts() as $own ) {
			if( $own === '' ) {
				continue;
			}
			if( $h === $own || substr( $h, -strlen( '.'.$own ) ) === '.'.$own ) {
				return true;
			}
		}
		return false;
	}

	public static function networkHostFromTrack( $pParams ) {
		if( empty( $pParams ) || !is_array( $pParams ) ) {
			return '';
		}
		$src = strtolower(
			( !empty( $pParams['utm_source'] ) ? $pParams['utm_source'] : '' ).' '.
			( !empty( $pParams['ctm_source'] ) ? $pParams['ctm_source'] : '' )
		);
		if( !empty( $pParams['msclkid'] ) || strpos( $src, 'bing' ) !== false || strpos( $src, 'microsoft' ) !== false ) {
			return 'www.bing.com';
		}
		if( !empty( $pParams['gclid'] ) || !empty( $pParams['gad_campaignid'] ) || strpos( $src, 'google' ) !== false ) {
			return 'www.google.com';
		}
		if( static::isPaidTracking( $pParams ) ) {
			return 'www.google.com';
		}
		return '';
	}

	/**
	 * This site must never be stored as a referer (paradox). Same-site URL
	 * with paid query is the landing; unpaid same-site referer is dropped.
	 */
	public static function canonicalizeFirstTouch( $pRefererUrl, $pLandingUri ) {
		$parsed = parse_url( (string)$pRefererUrl );
		if( empty( $parsed['host'] ) || !static::isOwnHost( $parsed['host'] ) ) {
			return array( $pRefererUrl, $pLandingUri );
		}
		$query = !empty( $parsed['query'] ) ? $parsed['query'] : '';
		$params = array();
		if( $query !== '' ) {
			parse_str( $query, $params );
		}
		$path = !empty( $parsed['path'] ) ? $parsed['path'] : '/';
		$fromRef = ( $query !== '' ) ? $path.'?'.$query : $path;
		if( static::isPaidTracking( $params ) ) {
			$landing = $fromRef;
			$net = static::networkHostFromTrack( $params );
			$referer = $net !== '' ? 'https://'.$net.'/' : 'https://www.google.com/';
			return array( $referer, $landing );
		}
		$landing = !empty( $pLandingUri ) ? $pLandingUri : $fromRef;
		return array( null, $landing );
	}

	public static function refererHostKey( $pRow ) {
		$url = !empty( $pRow['referer_url'] ) ? $pRow['referer_url'] : '';
		$host = 'none';
		$parsed = parse_url( $url );
		if( !empty( $parsed['host'] ) ) {
			$host = $parsed['host'];
		}
		if( static::isOwnHost( $host ) ) {
			$track = static::trackingParamsFromRow( $pRow );
			if( static::isPaidTracking( $track ) ) {
				$net = static::networkHostFromTrack( $track );
				return $net !== '' ? $net : 'www.google.com';
			}
			return 'none';
		}
		return $host;
	}

	/**
	 * Site page used as an organic/unpaid ad-group. Query (srsltid, gclid, …)
	 * is stripped so every hit on the same path lumps together.
	 */
	public static function landingPageKey( $pRow ) {
		$raw = '';
		if( !empty( $pRow['landing_url'] ) ) {
			$raw = $pRow['landing_url'];
		}
		if( $raw === '' ) {
			return '';
		}
		$qpos = strpos( $raw, '?' );
		if( $qpos !== false ) {
			$raw = substr( $raw, 0, $qpos );
		}
		$raw = rawurldecode( str_replace( '+', ' ', $raw ) );
		$raw = trim( $raw, '/' );
		return $raw === '' ? 'Home' : $raw;
	}

	/**
	 * Ad group from ctm_adgroup, else the /create/{slug} landing Google Ads uses.
	 */
	public static function inferredAdGroup( $pRow, $pTrack = array() ) {
		if( !empty( $pTrack['ctm_adgroup'] ) ) {
			return $pTrack['ctm_adgroup'];
		}
		$page = static::landingPageKey( $pRow );
		if( strpos( $page, 'create/' ) === 0 ) {
			$slug = substr( $page, 7 );
			$cut = strpos( $slug, '/' );
			if( $cut !== false ) {
				$slug = substr( $slug, 0, $cut );
			}
			$slug = trim( $slug );
			if( $slug !== '' ) {
				return str_replace( '-', ' ', $slug );
			}
		}
		return $page !== '' ? $page : '';
	}

	public static function isCreateLanding( $pRow ) {
		$page = static::landingPageKey( $pRow );
		return ( strpos( $page, 'create/' ) === 0 );
	}

	/**
	 * Google campaign_id → name from warehouse, if present.
	 */
	public static function googleCampaignMap() {
		global $gBitSystem;
		static $map = null;
		if( $map !== null ) {
			return $map;
		}
		$map = array();
		if( empty( $gBitSystem->mDb ) ) {
			return $map;
		}
		$has = $gBitSystem->mDb->getOne(
			"SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?",
			array( 'stats_ad_campaign' )
		);
		if( !$has ) {
			return $map;
		}
		$rows = $gBitSystem->mDb->getAll(
			"SELECT campaign_id, campaign_name FROM stats_ad_campaign WHERE network_code = ?",
			array( 'google' )
		);
		foreach( $rows as $row ) {
			$map[(string)$row['campaign_id']] = $row['campaign_name'];
		}
		return $map;
	}

	public static function inferredPpcCampaign( $pRow, $pTrack = array() ) {
		if( !empty( $pTrack['ctm_campaign'] ) ) {
			return $pTrack['ctm_campaign'];
		}
		$map = static::googleCampaignMap();
		foreach( array( 'gad_campaignid', 'utm_campaign' ) as $k ) {
			$id = !empty( $pTrack[$k] ) ? (string)$pTrack[$k] : '';
			if( $id !== '' && isset( $map[$id] ) ) {
				return $map[$id];
			}
		}
		if( static::isCreateLanding( $pRow ) ) {
			return 'untracked';
		}
		return 'Performance Max';
	}

	public static function trackingParamsFromRow( $pRow ) {
		$params = array();
		$landingQuery = !empty( $pRow['landing_query'] ) ? $pRow['landing_query'] : '';
		$landingUrl = !empty( $pRow['landing_url'] ) ? $pRow['landing_url'] : '';
		if( $landingQuery === '' && strpos( $landingUrl, '?' ) !== false ) {
			$parts = explode( '?', $landingUrl, 2 );
			$landingQuery = isset( $parts[1] ) ? $parts[1] : '';
		}
		if( $landingQuery !== '' ) {
			parse_str( $landingQuery, $params );
		}
		if( empty( $params ) && !empty( $pRow['referer_url'] ) ) {
			$parsed = parse_url( $pRow['referer_url'] );
			if( !empty( $parsed['query'] ) ) {
				parse_str( $parsed['query'], $refParams );
				if( !empty( $refParams['adurl'] ) ) {
					$ad = parse_url( $refParams['adurl'] );
					if( !empty( $ad['query'] ) ) {
						parse_str( $ad['query'], $params );
					} elseif( strpos( $refParams['adurl'], '?' ) === false && strpos( $refParams['adurl'], 'ctm_' ) !== false ) {
						parse_str( $refParams['adurl'], $params );
					}
				} else {
					$params = $refParams;
				}
			}
		}
		return $params;
	}

	public static function namedCtmCampaign( $pParams ) {
		return ( !empty( $pParams['ctm_campaign'] ) );
	}

	public static function isPaidTracking( $pParams ) {
		if( empty( $pParams ) || !is_array( $pParams ) ) {
			return false;
		}
		foreach( $pParams as $k => $v ) {
			if( strpos( $k, 'ctm_' ) === 0 ) {
				return true;
			}
		}
		foreach( array( 'gclid', 'msclkid', 'gad_source', 'gad_campaignid', 'gbraid', 'wbraid' ) as $k ) {
			if( !empty( $pParams[$k] ) ) {
				return true;
			}
		}
		if( !empty( $pParams['utm_source'] ) && strtolower( $pParams['utm_source'] ) == 'google' ) {
			return true;
		}
		return false;
	}

	function sortRefererHash( $a, $b ) {
		return count( $a ) < count( $b );
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
				if( !empty( $parsed['scheme'] ) ) {
					$now = $gBitSystem->getUTCTime();

					$store = $parsed['scheme'].'://'.$parsed['host'];

					$this->StartTrans();
					$query = "UPDATE `".BIT_DB_PREFIX."stats_referers` SET `hits`=`hits`+1,`last`=? WHERE `referer`=?";
					$this->mDb->query( $query, array( $now, $store ));

					// if the above didn't affect the db, we know that the entry doesn't exist yet.
					if( !$this->mDb->Affected_Rows() ) {
						$query = "INSERT INTO `".BIT_DB_PREFIX."stats_referers`( `last`, `referer`, `hits` ) VALUES( ?, ?, ? )";
						$this->mDb->query( $query, array( $now, $store, 1 ));
					}
					$this->CompleteTrans();
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
		$this->mDb->StartTrans(); // base method StartTrans will clearFromCache
		$query = "UPDATE `".BIT_DB_PREFIX."stats_pageviews` SET `pageviews`=`pageviews`+1 WHERE `stats_day`=?";
		$this->mDb->query( $query, array( $dayzero ));
		// if the above didn't affect the db, we know that the entry doesn't exist yet.
		if( !$this->mDb->Affected_Rows() ) {
			$query = "INSERT INTO `".BIT_DB_PREFIX."stats_pageviews`( `pageviews`, `stats_day` ) VALUES( ?, ? )";
			$this->mDb->query( $query, array( 1, $dayzero ));
		}
		$this->mDb->CompleteTrans();
	}

	/**
	 * registrationStats
	 *
	 * @param array $pPeriod
	 * @access public
	 * @return TRUE on success, FALSE on failure - mErrors will contain reason for failure
	 */
	function registrationStats( $pPeriodFormat ) {
		global $gBitDbType;

		$sqlPeriod = $this->mDb->SQLDate( $pPeriodFormat, $this->mDb->SQLIntToTimestamp( 'registration_date' ));
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
			if( $typeClass = $gLibertySystem->getContentClassName( $guid )) {
				$object = new $typeClass();
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
				$guid = $contentType['content_type_guid'];
				$hits = $this->mDb->getOne( "
					SELECT SUM(`hits`)
					FROM `".BIT_DB_PREFIX."liberty_content` lc
						LEFT OUTER JOIN `".BIT_DB_PREFIX."liberty_content_hits` liberty_content_hits
							ON (lc.`content_id` = liberty_content_hits.`content_id`)
					WHERE content_type_guid=?", array( $guid )
				);
				if( !empty( $hits )) {
					$ret['legend'][] = $gLibertySystem->getContentTypeName( $guid );
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
			$result = $this->mDb->query( $query, array( $pContentTypeGuid ), 40 );
			$tmpHash = array();
			// this is needed to ensure all arrays have same size
			while( $res = $result->fetchRow() ) {
				$tmpHash = array(
					$res['title'],
					$res['hits'],
				);
			}
			$ret['data'][$pContentTypeGuid] = array_chunk( $tmpHash, 40 );
			$ret['title'] = $gLibertySystem->getContentTypeName( $pContentTypeGuid );
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
				$i = 0;
				$tmpHash = array( NULL, NULL );
				while( $res = $result->fetchRow() ) {
					$tmpHash = array(
						$res['title'],
						$res['hits'],
					);
				}
			  $ret['data'][$contentType['content_type_guid']] = array_chunk( $tmpHash, 40 );
			}
			$ret['title'] = 'All Content';
		}
		return $ret;
	}
}
?>
