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

	require_once( STATS_PKG_CLASS_PATH.'Statistics.php' );
	require_once( dirname( __FILE__ ).'/stats_functions_inc.php' );
	$stats = new Statistics();
	if( $gBitSystem->isFeatureActive('stats_pageviews') ) {
		$stats->addPageview();
	}

	if( $gBitSystem->isFeatureActive( 'stats_referers' )) {
		if( !$gBitUser->isRegistered() ) {
			stats_capture_first_touch();
		}
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
		if( is_a( $pObject, 'BitUser' ) && !empty( $pObject->mUserId ) ) {
			stats_persist_registration_attribution( $pObject );
		}
	}

	function stats_referer_display_short( $pRefererUrl ) {
		$ret = '';
		if( ($urlHash = parse_url( $pRefererUrl )) && !empty( $urlHash['host'] ) ) {
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
//			$ret = tra( 'Unknown URL' );
		}
		return $ret;
	}
}
