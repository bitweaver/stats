<?php
/**
 * Ad warehouse API setup: required keys, how to obtain them, and
 * kernel_config storage. Never print secret values into templates.
 */

function stats_ads_setup_catalog() {
	return array(
		'google' => array(
			'label' => 'Google Ads',
			'wired' => true,
			'keys'  => array(
				'google_ads_customer_id'       => array( 'label' => 'Customer id', 'hint' => '10 digits, no dashes' ),
				'google_ads_login_customer_id' => array( 'label' => 'MCC / login customer id', 'hint' => 'Manager account id, no dashes' ),
				'google_ads_developer_token'   => array( 'label' => 'Developer token', 'hint' => 'MCC API Center. Sent as developer-token on every API call. Not a Bearer access token.' ),
				'google_ads_api_version'       => array( 'label' => 'API version', 'hint' => 'e.g. v22' ),
				'google_ads_sa_json'           => array( 'label' => 'Service account JSON', 'hint' => 'Paste the full JSON key. Nightly jobs mint a short-lived access token from this. Do not paste a Bearer token.', 'type' => 'textarea' ),
			),
			'steps' => array(
				'Google does not use a long-lived API auth token. Nightly pulls mint a one-hour access token from the service account JSON below, and send the developer token as a header.',
				'In Google Cloud, enable Google Ads API on the project that owns the service account. Create a service account and download its JSON key.',
				'On the Google Ads MCC: Admin → Access and security → add the service account email as Read-only.',
				'Paste the JSON key into Service account JSON on this page (not a file path). Paste the MCC developer token into Developer token.',
				'Copy the MCC id and the client customer id (digits only) into the fields below.',
				'Production cron is stats/admin/sh_ad_warehouse_pull.php in this package (deployed with the site), not a developer workspace path.',
			),
		),
		'microsoft' => array(
			'label' => 'Microsoft Advertising',
			'wired' => true,
			'keys'  => array(
				'microsoft_ads_developer_token' => array( 'label' => 'Developer token', 'hint' => 'From the Microsoft Advertising developer portal' ),
				'microsoft_ads_client_id'       => array( 'label' => 'Azure application (client) id', 'hint' => 'App registration' ),
				'microsoft_ads_client_secret'   => array( 'label' => 'Azure client secret', 'hint' => 'Certificates & secrets' ),
				'microsoft_ads_refresh_token'   => array( 'label' => 'OAuth refresh token', 'hint' => 'Use Sign in below after client id and secret are saved' ),
				'microsoft_ads_customer_id'     => array( 'label' => 'Customer id', 'hint' => 'Digits from Microsoft Ads (customer, not account)' ),
				'microsoft_ads_account_id'      => array( 'label' => 'Account id', 'hint' => 'Digits from Microsoft Ads account settings' ),
			),
			'steps' => array(
				'In Microsoft Advertising (super admin): Tools → Developer, request or copy a developer token. https://developers.ads.microsoft.com/',
				'In Azure: Microsoft Entra ID → App registrations → New registration. Accounts in any organizational directory. Platform: Web.',
				'Set the Web redirect URI to the exact URL shown on this page (copy Redirect URI below).',
				'Certificates & secrets → New client secret. Copy the secret value once.',
				'Save client id, client secret, and developer token on this page, then click Sign in to Microsoft Advertising.',
				'Consent with a user who can see this install\'s ads account. This page stores the refresh token; it is never shown again.',
				'Copy Customer id and Account id from Microsoft Ads UI (numbers only, no dashes) into the fields below.',
			),
		),
		'meta' => array(
			'label' => 'Meta Ads',
			'wired' => false,
			'keys'  => array(),
			'steps' => array( 'Adapter is not wired. Warehouse network stub exists.' ),
		),
		'tiktok' => array(
			'label' => 'TikTok Ads',
			'wired' => false,
			'keys'  => array(),
			'steps' => array( 'Adapter is not wired. Warehouse network stub exists.' ),
		),
	);
}

function stats_ads_all_keys() {
	$keys = array();
	foreach( stats_ads_setup_catalog() as $net ) {
		foreach( array_keys( $net['keys'] ) as $k ) {
			$keys[] = $k;
		}
	}
	return $keys;
}

function stats_ads_setup_redirect_uri() {
	if( defined( 'STATS_PKG_URI' ) && preg_match( '#^https?://#i', STATS_PKG_URI ) ) {
		return STATS_PKG_URI.'ad_setup.php';
	}
	$https = true;
	if( isset( $_SERVER['HTTPS'] ) && ( $_SERVER['HTTPS'] === 'off' || $_SERVER['HTTPS'] === '' ) ) {
		$https = false;
	}
	$host = !empty( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
	$path = defined( 'STATS_PKG_URL' ) ? STATS_PKG_URL : '/stats/';
	if( preg_match( '#^https?://#i', $path ) ) {
		return rtrim( $path, '/' ).'/ad_setup.php';
	}
	return ( $https ? 'https' : 'http' ).'://'.$host.$path.'ad_setup.php';
}

function stats_ads_mask( $pValue ) {
	if( $pValue === null || $pValue === '' ) {
		return '';
	}
	$len = strlen( $pValue );
	$trim = ltrim( $pValue );
	if( $trim !== '' && $trim[0] === '{' ) {
		return 'set (JSON, '.$len.' chars)';
	}
	if( $len <= 4 ) {
		return 'set ('.$len.' chars)';
	}
	return 'set, last '.substr( $pValue, -4 );
}

function stats_ads_get_secret( $pKey ) {
	if( function_exists( 'ads_get_secret' ) ) {
		return ads_get_secret( $pKey );
	}
	global $gBitSystem;
	$v = $gBitSystem->getConfig( $pKey );
	if( $v !== null && $v !== '' ) {
		return $v;
	}
	return null;
}

function stats_ads_status_rows() {
	$rows = array();
	foreach( stats_ads_setup_catalog() as $code => $net ) {
		foreach( $net['keys'] as $key => $meta ) {
			$val = stats_ads_get_secret( $key );
			$rows[] = array(
				'network' => $code,
				'network_label' => $net['label'],
				'key' => $key,
				'label' => $meta['label'],
				'hint' => $meta['hint'],
				'set' => ( $val !== null && $val !== '' ),
				'mask' => stats_ads_mask( $val ),
			);
		}
	}
	return $rows;
}

function stats_ads_save_posted_secrets() {
	if( empty( $_POST['ads_secret'] ) || !is_array( $_POST['ads_secret'] ) ) {
		return 0;
	}
	if( function_exists( 'ads_apply_warehouse_schema' ) ) {
		ads_apply_warehouse_schema();
	}
	$allowed = array_flip( stats_ads_all_keys() );
	$n = 0;
	foreach( $_POST['ads_secret'] as $k => $v ) {
		if( !isset( $allowed[$k] ) ) {
			continue;
		}
		$v = trim( (string)$v );
		if( $v === '' ) {
			continue;
		}
		if( function_exists( 'ads_store_secret' ) ) {
			ads_store_secret( $k, $v );
		} else {
			global $gBitSystem;
			$gBitSystem->storeConfig( $k, $v, STATS_PKG_NAME );
		}
		$n++;
	}
	return $n;
}

function stats_ads_microsoft_authorize_url( $pState ) {
	$clientId = stats_ads_get_secret( 'microsoft_ads_client_id' );
	if( !$clientId ) {
		return null;
	}
	$q = http_build_query( array(
		'client_id'     => $clientId,
		'response_type' => 'code',
		'redirect_uri'  => stats_ads_setup_redirect_uri(),
		'response_mode' => 'query',
		'scope'         => 'https://ads.microsoft.com/msads.manage offline_access',
		'state'         => $pState,
		'prompt'        => 'select_account',
	) );
	return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?'.$q;
}

function stats_ads_microsoft_exchange_code( $pCode ) {
	$clientId = stats_ads_get_secret( 'microsoft_ads_client_id' );
	$secret = stats_ads_get_secret( 'microsoft_ads_client_secret' );
	if( !$clientId || !$secret ) {
		return 'Save Azure client id and client secret first.';
	}
	$ch = curl_init( 'https://login.microsoftonline.com/common/oauth2/v2.0/token' );
	curl_setopt_array( $ch, array(
		CURLOPT_POST           => true,
		CURLOPT_POSTFIELDS     => http_build_query( array(
			'client_id'     => $clientId,
			'client_secret' => $secret,
			'code'          => $pCode,
			'redirect_uri'  => stats_ads_setup_redirect_uri(),
			'grant_type'    => 'authorization_code',
			'scope'         => 'https://ads.microsoft.com/msads.manage offline_access',
		) ),
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER     => array( 'Content-Type: application/x-www-form-urlencoded' ),
		CURLOPT_TIMEOUT        => 30,
	) );
	$body = curl_exec( $ch );
	$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );
	$j = json_decode( $body, true );
	if( $code >= 300 || empty( $j['refresh_token'] ) ) {
		$err = isset( $j['error_description'] ) ? $j['error_description'] : ( isset( $j['error'] ) ? $j['error'] : 'HTTP '.$code );
		return 'Microsoft token exchange failed: '.$err;
	}
	if( function_exists( 'ads_apply_warehouse_schema' ) ) {
		ads_apply_warehouse_schema();
	}
	if( function_exists( 'ads_store_secret' ) ) {
		ads_store_secret( 'microsoft_ads_refresh_token', $j['refresh_token'] );
	} else {
		global $gBitSystem;
		$gBitSystem->storeConfig( 'microsoft_ads_refresh_token', $j['refresh_token'], STATS_PKG_NAME );
	}
	return true;
}
