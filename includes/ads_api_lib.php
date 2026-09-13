<?php
/**
 * Read-only Google Ads API helper. Never uploads conversions.
 * Credentials from site config (stats Ad API setup). Optional leftover
 * file is only used if still readable; production uses kernel_config.
 */

function ads_secret_key_names() {
	return array(
		'google_ads_customer_id', 'google_ads_login_customer_id', 'google_ads_developer_token',
		'google_ads_api_version', 'google_ads_sa_json',
		'microsoft_ads_developer_token', 'microsoft_ads_client_id', 'microsoft_ads_client_secret',
		'microsoft_ads_refresh_token', 'microsoft_ads_customer_id', 'microsoft_ads_account_id',
	);
}

function ads_secrets_table_ready() {
	global $gBitSystem;
	return is_object( $gBitSystem ) && (bool)$gBitSystem->mDb->getOne(
		"SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='stats_prefs'"
	);
}

function ads_store_secret( $pKey, $pValue ) {
	global $gBitSystem;
	if( !ads_secrets_table_ready() ) {
		throw new Exception( 'stats_prefs is missing; apply warehouse schema first' );
	}
	$gBitSystem->mDb->query(
		"INSERT INTO stats_prefs (pref_name, pref_value, updated_at) VALUES (?, ?, now())
		 ON CONFLICT (pref_name) DO UPDATE SET pref_value = EXCLUDED.pref_value, updated_at = now()",
		array( $pKey, $pValue )
	);
}

function ads_get_secret( $pKey ) {
	global $gBitSystem;
	if( ads_secrets_table_ready() ) {
		$v = $gBitSystem->mDb->getOne(
			"SELECT pref_value FROM stats_prefs WHERE pref_name = ?",
			array( $pKey )
		);
		if( $v !== null && $v !== '' ) {
			return $v;
		}
	}
	return null;
}

function ads_import_secrets_file( $pPath ) {
	if( !is_readable( $pPath ) ) {
		throw new Exception( 'Secrets file is not readable' );
	}
	$n = 0;
	foreach( file( $pPath, FILE_IGNORE_NEW_LINES ) as $line ) {
		if( $line === '' || $line[0] === '#' || strpos( $line, ': ' ) === false ) {
			continue;
		}
		list( $k, $v ) = explode( ': ', $line, 2 );
		$k = trim( $k );
		$v = trim( $v );
		if( $k === '' || $v === '' || !in_array( $k, ads_secret_key_names(), true ) ) {
			continue;
		}
		if( $k === 'google_ads_sa_json' && isset( $v[0] ) && $v[0] !== '{' && is_readable( $v ) ) {
			$v = file_get_contents( $v );
		}
		ads_store_secret( $k, $v );
		$n++;
	}
	return $n;
}

function ads_read_secrets() {
	$ret = array();
	foreach( ads_secret_key_names() as $k ) {
		$c = ads_get_secret( $k );
		if( $c !== null && $c !== '' ) {
			$ret[$k] = $c;
		}
	}
	return $ret;
}

function ads_load_sa_json() {
	$s = ads_read_secrets();
	$raw = !empty( $s['google_ads_sa_json'] ) ? trim( $s['google_ads_sa_json'] ) : '';
	if( $raw === '' ) {
		throw new Exception( 'Set google_ads_sa_json on Ad API setup (paste the service account JSON).' );
	}
	if( $raw[0] !== '{' ) {
		$path = $raw;
		if( $path[0] !== '/' && defined( 'BIT_ROOT_PATH' ) ) {
			$path = BIT_ROOT_PATH.$path;
		}
		if( !is_readable( $path ) ) {
			throw new Exception( 'google_ads_sa_json is not JSON and is not a readable file' );
		}
		$raw = file_get_contents( $path );
	}
	$j = json_decode( $raw, true );
	if( empty( $j['client_email'] ) || empty( $j['private_key'] ) ) {
		throw new Exception( 'SA JSON missing client_email/private_key' );
	}
	return $j;
}

function ads_b64url( $data ) {
	return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}

function ads_access_token() {
	$sa = ads_load_sa_json();
	$now = time();
	$header = ads_b64url( json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
	$claims = ads_b64url( json_encode( array(
		'iss'   => $sa['client_email'],
		'scope' => 'https://www.googleapis.com/auth/adwords',
		'aud'   => 'https://oauth2.googleapis.com/token',
		'exp'   => $now + 3600,
		'iat'   => $now,
	) ) );
	$unsigned = $header.'.'.$claims;
	$ok = openssl_sign( $unsigned, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256 );
	if( !$ok ) {
		throw new Exception( 'openssl_sign failed' );
	}
	$jwt = $unsigned.'.'.ads_b64url( $sig );
	$ch = curl_init( 'https://oauth2.googleapis.com/token' );
	curl_setopt_array( $ch, array(
		CURLOPT_POST           => true,
		CURLOPT_POSTFIELDS     => http_build_query( array(
			'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
			'assertion'  => $jwt,
		) ),
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER     => array( 'Content-Type: application/x-www-form-urlencoded' ),
		CURLOPT_TIMEOUT        => 30,
	) );
	$body = curl_exec( $ch );
	$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );
	$j = json_decode( $body, true );
	if( $code >= 300 || empty( $j['access_token'] ) ) {
		throw new Exception( 'token HTTP '.$code.' '.$body );
	}
	return $j['access_token'];
}

function ads_api_version() {
	$s = ads_read_secrets();
	return !empty( $s['google_ads_api_version'] ) ? $s['google_ads_api_version'] : 'v22';
}

function ads_headers( $token ) {
	$s = ads_read_secrets();
	$h = array(
		'Authorization: Bearer '.$token,
		'Content-Type: application/json',
	);
	if( !empty( $s['google_ads_developer_token'] ) ) {
		$h[] = 'developer-token: '.$s['google_ads_developer_token'];
	}
	if( !empty( $s['google_ads_login_customer_id'] ) ) {
		$h[] = 'login-customer-id: '.preg_replace( '/\D/', '', $s['google_ads_login_customer_id'] );
	}
	return $h;
}

function ads_list_accessible_customers( $token ) {
	$ver = ads_api_version();
	$ch = curl_init( 'https://googleads.googleapis.com/'.$ver.'/customers:listAccessibleCustomers' );
	curl_setopt_array( $ch, array(
		CURLOPT_HTTPGET        => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER     => ads_headers( $token ),
		CURLOPT_TIMEOUT        => 60,
	) );
	$body = curl_exec( $ch );
	$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );
	return array( $code, $body );
}

function ads_search_each( $token, $customerId, $query, $callback, $pageSize = 5000 ) {
	$ver = ads_api_version();
	$cid = preg_replace( '/\D/', '', $customerId );
	$url = 'https://googleads.googleapis.com/'.$ver.'/customers/'.$cid.'/googleAds:search';
	$pageToken = null;
	$n = 0;
	do {
		$payload = array( 'query' => $query );
		if( $pageToken ) {
			$payload['pageToken'] = $pageToken;
		}
		$ch = curl_init( $url );
		curl_setopt_array( $ch, array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => json_encode( $payload ),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => ads_headers( $token ),
			CURLOPT_TIMEOUT        => 180,
		) );
		$body = curl_exec( $ch );
		$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );
		if( $code >= 300 ) {
			throw new Exception( 'search HTTP '.$code.' '.substr( $body, 0, 800 ) );
		}
		$j = json_decode( $body, true );
		unset( $body );
		if( !is_array( $j ) ) {
			throw new Exception( 'search not JSON' );
		}
		if( !empty( $j['results'] ) && is_array( $j['results'] ) ) {
			foreach( $j['results'] as $row ) {
				$callback( $row );
				$n++;
			}
		}
		$pageToken = !empty( $j['nextPageToken'] ) ? $j['nextPageToken'] : null;
		unset( $j );
	} while( $pageToken );
	return $n;
}

function ads_search_stream( $token, $customerId, $query ) {
	$ver = ads_api_version();
	$cid = preg_replace( '/\D/', '', $customerId );
	$url = 'https://googleads.googleapis.com/'.$ver.'/customers/'.$cid.'/googleAds:searchStream';
	$ch = curl_init( $url );
	curl_setopt_array( $ch, array(
		CURLOPT_POST           => true,
		CURLOPT_POSTFIELDS     => json_encode( array( 'query' => $query ) ),
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER     => ads_headers( $token ),
		CURLOPT_TIMEOUT        => 600,
	) );
	$body = curl_exec( $ch );
	$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );
	if( $code >= 300 ) {
		throw new Exception( 'searchStream HTTP '.$code.' '.$body );
	}
	$chunks = json_decode( $body, true );
	if( !is_array( $chunks ) ) {
		throw new Exception( 'searchStream not JSON: '.substr( $body, 0, 400 ) );
	}
	// REST searchStream is an array of {results:[...]} objects
	if( isset( $chunks['results'] ) ) {
		return $chunks['results'];
	}
	$out = array();
	foreach( $chunks as $chunk ) {
		if( !empty( $chunk['results'] ) && is_array( $chunk['results'] ) ) {
			foreach( $chunk['results'] as $row ) {
				$out[] = $row;
			}
		}
	}
	return $out;
}

function ads_customer_id() {
	$s = ads_read_secrets();
	if( !empty( $s['google_ads_customer_id'] ) ) {
		return preg_replace( '/\D/', '', $s['google_ads_customer_id'] );
	}
	return null;
}
