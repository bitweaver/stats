<?php
global $gBitSystem, $gBitUser ;

$registerHash = array(
	'package_name' => 'stats',
	'package_path' => dirname( dirname( __FILE__ ) ).'/',
);
$gBitSystem->registerPackage( $registerHash );

if( $gBitSystem->isPackageActive( 'stats' )) {
	if( $gBitUser->hasPermission( 'p_stats_view' ) || $gBitUser->hasPermission( 'p_stats_view_referer' ) ) {
		$menuHash = array(
			'package_name'  => STATS_PKG_NAME,
			'index_url'     => STATS_PKG_URL.'index.php',
			'menu_template' => 'bitpackage:stats/menu_stats.tpl',
		);
		$gBitSystem->registerAppMenu( $menuHash );
	}

	$gLibertySystem->registerService( STATS_PKG_NAME, STATS_PKG_NAME, array(
			'users_expunge_function'	=> 'stats_user_expunge',
			'users_register_function'   => 'stats_user_register',
	) );

	require_once( STATS_PKG_PATH.'Statistics.php' );
	$stats = new Statistics();
	if( $gBitSystem->isFeatureActive('stats_pageviews') ) {
		$stats->addPageview();
	}

	if( !$gBitUser->isRegistered() && !empty( $_SERVER['HTTP_REFERER'] )  && strlen( $_SERVER['HTTP_REFERER'] ) > 9 ) {
		// Explode the HTTP_REFERER address to split up the string 
		if( $ref = explode('/', $_SERVER['HTTP_REFERER']) ) {
			if( $ref[2] != $_SERVER['HTTP_HOST'] ) {
				// we have a standard refering URL
				if( empty( $_COOKIE['referer_url'] ) ) {
					setcookie( 'referer_url', $_SERVER['HTTP_REFERER'], time()+60*60*24*180, $gBitSystem->getConfig( 'cookie_path', BIT_ROOT_URL ), $gBitSystem->getConfig( 'cookie_domain', '' ));
				}
			}
		}
	}

	// store referer stats if desired
	if( $gBitSystem->isFeatureActive( 'stats_referers' )) {
		$stats->storeReferer();
	}

	// make sure all referrals are removed
	function stats_user_expunge( &$pObject ) {
		if( is_a( $pObject, 'BitUser' ) && !empty( $pObject->mUserId ) ) {
			$pObject->StartTrans();
			$pObject->mDb->query( "DELETE FROM `".BIT_DB_PREFIX."stats_referer_users_map` WHERE user_id=?", array( $pObject->mUserId ) );
			$pObject->CompleteTrans();
		}
	}
	
	function stats_user_register( &$pObject ) {
		if( !empty( $_COOKIE['referer_url'] ) && is_a( $pObject, 'BitUser' ) && !empty( $pObject->mUserId ) ) {
			$pObject->StartTrans();
			if( !$refererId = $pObject->mDb->getOne( "SELECT `referer_url_id` FROM `".BIT_DB_PREFIX."stats_referer_urls` WHERE `referer_url`=?", array(  $_COOKIE['referer_url'] ) ) ) {
				$refererId = $pObject->mDb->GenID( 'stats_referer_url_id_seq' );
				$pObject->mDb->query( "INSERT INTO `".BIT_DB_PREFIX."stats_referer_urls` (`referer_url_id`,`referer_url`) VALUES(?,?)", array( $refererId, $_COOKIE['referer_url'] ) );
			}
			$pObject->mDb->query( "INSERT INTO `".BIT_DB_PREFIX."stats_referer_users_map` (`user_id`,`referer_url_id`) VALUES(?,?)", array( $pObject->mUserId, $refererId ) );
			$pObject->CompleteTrans();
		}
	}

	function stats_referer_display_short( $pRefererUrl ) {
		$ret = '';
		if( $urlHash = parse_url( $pRefererUrl ) ) {
			$ret = $urlHash['host'];
			// q= google and bing search param, p= yahoo search param
			$searchStrings = array( 'q', 'p' );
			foreach( $searchStrings as $param ) {
				if( !empty( $urlHash['query'] ) && strpos( $urlHash['query'], $param.'=' ) !== FALSE ) {
					$result = array();
					parse_str( $urlHash['query'], $result );
					if( !empty( $result[$param] ) ) {
						$ret .= '/...'.$param.'='.$result[$param];
					}
				}
			}
		} else {
			$ret = tra( 'Unknown URL' );
		}
		return $ret;
	}
}
