<?php
/**
 * First-touch referrer and landing helpers. HTTP_REFERER is the source host
 * (often stripped to scheme://host). The landing cookie is the first request
 * URI (path + query). Tracking keys such as ctm_* / gclid live on that landing
 * query, never on the referer. Neither cookie is overwritten once set.
 */

function stats_capture_first_touch() {
	if( empty( $_COOKIE['referer_url'] ) && !empty( $_SERVER['HTTP_REFERER'] ) && strlen( $_SERVER['HTTP_REFERER'] ) > 9 ) {
		$ref = explode( '/', $_SERVER['HTTP_REFERER'] );
		if( count( $ref ) > 2 && !empty( $ref[2] ) && !stats_is_own_referer_host( $ref[2] ) ) {
			stats_set_first_touch_cookie( 'referer_url', $_SERVER['HTTP_REFERER'] );
		}
	}
	if( empty( $_COOKIE['landing_url'] ) ) {
		$landing = stats_landing_from_request();
		if( !empty( $landing ) ) {
			stats_set_first_touch_cookie( 'landing_url', $landing );
		}
	}
}

function stats_set_first_touch_cookie( $pName, $pValue ) {
	global $gBitSystem;
	$pValue = substr( $pValue, 0, 4000 );
	$opts = array(
		'expires'  => time() + 60 * 60 * 24 * 180,
		'path'     => $gBitSystem->getConfig( 'cookie_path', BIT_ROOT_URL ),
		'domain'   => $gBitSystem->getConfig( 'cookie_domain', '' ),
		'secure'   => ( !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ),
		'httponly' => true,
		'samesite' => 'Lax',
	);
	setcookie( $pName, $pValue, $opts );
	$_COOKIE[$pName] = $pValue;
}

function stats_request_has_tracking_query() {
	if( empty( $_GET ) || !is_array( $_GET ) ) {
		return false;
	}
	$keys = array( 'gclid', 'msclkid', 'gad_source', 'gad_campaignid', 'gbraid', 'wbraid',
		'utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term' );
	foreach( $_GET as $k => $v ) {
		if( strpos( $k, 'ctm_' ) === 0 || in_array( $k, $keys, true ) ) {
			return true;
		}
	}
	return false;
}

function stats_landing_from_request() {
	$uri = !empty( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
	if( $uri === '' || $uri[0] != '/' ) {
		return '';
	}
	return substr( $uri, 0, 4000 );
}

function stats_split_landing( $pLanding ) {
	$parts = explode( '?', $pLanding, 2 );
	return array( $parts[0], isset( $parts[1] ) ? $parts[1] : null );
}

function stats_persist_registration_attribution( &$pObject ) {
	$refererUrl = !empty( $_COOKIE['referer_url'] ) ? substr( $_COOKIE['referer_url'], 0, 4000 ) : null;
	$landingUri = !empty( $_COOKIE['landing_url'] ) ? substr( $_COOKIE['landing_url'], 0, 4000 ) : null;
	stats_store_user_first_touch( $pObject->mDb, $pObject->mUserId, $refererUrl, $landingUri, false );
}

/**
 * Insert or fill first-touch map for a user. Tracking keys belong on the
 * landing URI, not the referer. $pOverwrite replaces an existing map row.
 */
function stats_is_own_referer_host( $pHost ) {
	if( class_exists( 'Statistics', false ) ) {
		return Statistics::isOwnHost( $pHost );
	}
	$h = strtolower( preg_replace( '/:\d+$/', '', (string)$pHost ) );
	$h = preg_replace( '/^www\./', '', $h );
	$mine = array();
	if( !empty( $_SERVER['HTTP_HOST'] ) ) {
		$mine[] = preg_replace( '/^www\./', '', strtolower( $_SERVER['HTTP_HOST'] ) );
	}
	global $gBitSystem;
	if( is_object( $gBitSystem ) ) {
		$ks = $gBitSystem->getConfig( 'kernel_server_name' );
		if( !empty( $ks ) ) {
			$mine[] = preg_replace( '/^www\./', '', strtolower( $ks ) );
		}
	}
	foreach( $mine as $own ) {
		if( $own !== '' && ( $h === $own || substr( $h, -strlen( '.'.$own ) ) === '.'.$own ) ) {
			return true;
		}
	}
	return false;
}

function stats_store_user_first_touch( $pDb, $pUserId, $pRefererUrl, $pLandingUri, $pOverwrite = false ) {
	$pUserId = (int)$pUserId;
	if( $pUserId < 1 ) {
		return false;
	}
	if( class_exists( 'Statistics', false ) ) {
		list( $pRefererUrl, $pLandingUri ) = Statistics::canonicalizeFirstTouch( $pRefererUrl, $pLandingUri );
	} elseif( !empty( $pRefererUrl ) ) {
		$p = parse_url( $pRefererUrl );
		if( !empty( $p['host'] ) && stats_is_own_referer_host( $p['host'] ) ) {
			$pRefererUrl = null;
		}
	}
	$refererUrl = !empty( $pRefererUrl ) ? substr( $pRefererUrl, 0, 4000 ) : null;
	$landingUri = !empty( $pLandingUri ) ? substr( $pLandingUri, 0, 4000 ) : null;
	if( empty( $refererUrl ) && empty( $landingUri ) ) {
		return false;
	}
	$pDb->StartTrans();
	$refererId = null;
	$landingId = null;
	if( !empty( $refererUrl ) ) {
		$refererId = $pDb->getOne( "SELECT `referer_url_id` FROM `".BIT_DB_PREFIX."stats_referer_urls` WHERE `referer_url`=?", array( $refererUrl ) );
		if( !$refererId ) {
			$refererId = $pDb->GenID( 'stats_referer_url_id_seq' );
			$pDb->query( "INSERT INTO `".BIT_DB_PREFIX."stats_referer_urls` (`referer_url_id`,`referer_url`) VALUES(?,?)", array( $refererId, $refererUrl ) );
		}
	}
	if( !empty( $landingUri ) ) {
		list( $landingPath, $landingQuery ) = stats_split_landing( $landingUri );
		if( $landingPath === '' ) {
			$landingPath = $landingUri;
			$landingQuery = null;
		}
		if( $landingQuery === null ) {
			$landingId = $pDb->getOne( "SELECT `landing_url_id` FROM `".BIT_DB_PREFIX."stats_landing_urls` WHERE `landing_url`=? AND `landing_query` IS NULL", array( $landingPath ) );
		} else {
			$landingId = $pDb->getOne( "SELECT `landing_url_id` FROM `".BIT_DB_PREFIX."stats_landing_urls` WHERE `landing_url`=? AND `landing_query`=?", array( $landingPath, $landingQuery ) );
		}
		if( !$landingId ) {
			$landingId = $pDb->GenID( 'stats_landing_url_id_seq' );
			$pDb->query(
				"INSERT INTO `".BIT_DB_PREFIX."stats_landing_urls` (`landing_url_id`,`landing_url`,`landing_query`) VALUES(?,?,?)",
				array( $landingId, $landingPath, $landingQuery )
			);
		}
	}
	$existing = $pDb->getRow( "SELECT `referer_url_id`,`landing_url_id` FROM `".BIT_DB_PREFIX."stats_referer_users_map` WHERE `user_id`=?", array( $pUserId ) );
	if( $existing && !$pOverwrite ) {
		$newRef = !empty( $existing['referer_url_id'] ) ? $existing['referer_url_id'] : $refererId;
		$newLand = !empty( $existing['landing_url_id'] ) ? $existing['landing_url_id'] : $landingId;
		$pDb->query(
			"UPDATE `".BIT_DB_PREFIX."stats_referer_users_map` SET `referer_url_id`=?, `landing_url_id`=? WHERE `user_id`=?",
			array( $newRef, $newLand, $pUserId )
		);
	} elseif( $refererId || $landingId ) {
		if( empty( $refererId ) ) {
			$refererId = $pDb->getOne( "SELECT `referer_url_id` FROM `".BIT_DB_PREFIX."stats_referer_urls` WHERE `referer_url`=?", array( 'https://unknown/' ) );
			if( !$refererId ) {
				$refererId = $pDb->GenID( 'stats_referer_url_id_seq' );
				$pDb->query( "INSERT INTO `".BIT_DB_PREFIX."stats_referer_urls` (`referer_url_id`,`referer_url`) VALUES(?,?)", array( $refererId, 'https://unknown/' ) );
			}
		}
		if( $existing ) {
			$pDb->query(
				"UPDATE `".BIT_DB_PREFIX."stats_referer_users_map` SET `referer_url_id`=?, `landing_url_id`=? WHERE `user_id`=?",
				array( $refererId, $landingId, $pUserId )
			);
		} else {
			$pDb->query(
				"INSERT INTO `".BIT_DB_PREFIX."stats_referer_users_map` (`user_id`,`referer_url_id`,`landing_url_id`) VALUES(?,?,?)",
				array( $pUserId, $refererId, $landingId )
			);
		}
	}
	$pDb->CompleteTrans();
	return true;
}
