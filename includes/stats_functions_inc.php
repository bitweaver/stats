<?php
/**
 * First-touch referrer and landing helpers. HTTP_REFERER is the source host
 * (often stripped to scheme://host). Tracking keys such as ctm_* live on the
 * landing request URI. Neither cookie is overwritten once set.
 */

function stats_capture_first_touch() {
	if( empty( $_COOKIE['referer_url'] ) && !empty( $_SERVER['HTTP_REFERER'] ) && strlen( $_SERVER['HTTP_REFERER'] ) > 9 ) {
		$ref = explode( '/', $_SERVER['HTTP_REFERER'] );
		if( count( $ref ) > 2 && !empty( $ref[2] ) && $ref[2] != $_SERVER['HTTP_HOST'] ) {
			stats_set_first_touch_cookie( 'referer_url', $_SERVER['HTTP_REFERER'] );
		}
	}
	if( empty( $_COOKIE['landing_url'] ) && stats_request_has_tracking_query() ) {
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
	if( empty( $refererUrl ) && empty( $landingUri ) ) {
		return;
	}
	$pObject->StartTrans();
	$refererId = null;
	$landingId = null;
	if( !empty( $refererUrl ) ) {
		$refererId = $pObject->mDb->getOne( "SELECT `referer_url_id` FROM `".BIT_DB_PREFIX."stats_referer_urls` WHERE `referer_url`=?", array( $refererUrl ) );
		if( !$refererId ) {
			$refererId = $pObject->mDb->GenID( 'stats_referer_url_id_seq' );
			$pObject->mDb->query( "INSERT INTO `".BIT_DB_PREFIX."stats_referer_urls` (`referer_url_id`,`referer_url`) VALUES(?,?)", array( $refererId, $refererUrl ) );
		}
	}
	if( !empty( $landingUri ) ) {
		list( $landingPath, $landingQuery ) = stats_split_landing( $landingUri );
		if( $landingQuery === null ) {
			$landingId = $pObject->mDb->getOne( "SELECT `landing_url_id` FROM `".BIT_DB_PREFIX."stats_landing_urls` WHERE `landing_url`=? AND `landing_query` IS NULL", array( $landingPath ) );
		} else {
			$landingId = $pObject->mDb->getOne( "SELECT `landing_url_id` FROM `".BIT_DB_PREFIX."stats_landing_urls` WHERE `landing_url`=? AND `landing_query`=?", array( $landingPath, $landingQuery ) );
		}
		if( !$landingId ) {
			$landingId = $pObject->mDb->GenID( 'stats_landing_url_id_seq' );
			$pObject->mDb->query(
				"INSERT INTO `".BIT_DB_PREFIX."stats_landing_urls` (`landing_url_id`,`landing_url`,`landing_query`) VALUES(?,?,?)",
				array( $landingId, $landingPath, $landingQuery )
			);
		}
	}
	$existing = $pObject->mDb->getRow( "SELECT `referer_url_id`,`landing_url_id` FROM `".BIT_DB_PREFIX."stats_referer_users_map` WHERE `user_id`=?", array( $pObject->mUserId ) );
	if( $existing ) {
		$newRef = !empty( $existing['referer_url_id'] ) ? $existing['referer_url_id'] : $refererId;
		$newLand = !empty( $existing['landing_url_id'] ) ? $existing['landing_url_id'] : $landingId;
		$pObject->mDb->query(
			"UPDATE `".BIT_DB_PREFIX."stats_referer_users_map` SET `referer_url_id`=?, `landing_url_id`=? WHERE `user_id`=?",
			array( $newRef, $newLand, $pObject->mUserId )
		);
	} elseif( $refererId || $landingId ) {
		if( empty( $refererId ) ) {
			$refererId = $pObject->mDb->getOne( "SELECT `referer_url_id` FROM `".BIT_DB_PREFIX."stats_referer_urls` WHERE `referer_url`=?", array( 'https://unknown/' ) );
			if( !$refererId ) {
				$refererId = $pObject->mDb->GenID( 'stats_referer_url_id_seq' );
				$pObject->mDb->query( "INSERT INTO `".BIT_DB_PREFIX."stats_referer_urls` (`referer_url_id`,`referer_url`) VALUES(?,?)", array( $refererId, 'https://unknown/' ) );
			}
		}
		$pObject->mDb->query(
			"INSERT INTO `".BIT_DB_PREFIX."stats_referer_users_map` (`user_id`,`referer_url_id`,`landing_url_id`) VALUES(?,?,?)",
			array( $pObject->mUserId, $refererId, $landingId )
		);
	}
	$pObject->CompleteTrans();
}
