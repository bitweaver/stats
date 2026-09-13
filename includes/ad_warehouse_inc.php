<?php
/**
 * Shared warehouse helpers. Dev writes db2 test; live writes the site DB.
 * No Google conversion uploads.
 */

function ads_bootstrap_bitweaver() {
	throw new Exception( 'Use warehouse_bootstrap.php at file scope; setup_inc.php cannot load inside a function' );
}

function ads_require_db2_test() {
	return ads_require_warehouse_db();
}

function ads_require_warehouse_db() {
	global $gBitSystem;
	$row = $gBitSystem->mDb->getRow( 'SELECT inet_server_addr() AS addr, current_database() AS db' );
	$db = isset( $row['db'] ) ? $row['db'] : '';
	$addr = isset( $row['addr'] ) ? $row['addr'] : '';
	$isDev = !empty( $_SERVER['IS_DEV'] ) || getenv( 'IS_DEV' );
	$isLive = !empty( $_SERVER['IS_LIVE'] ) || getenv( 'IS_LIVE' );
	if( $isDev ) {
		$expect = gethostbyname( 'db2.colo.printmotive.com' );
		if( $addr !== $expect || !preg_match( '/test$/', $db ) ) {
			throw new Exception( 'Refusing warehouse write: expected db2 test, got addr='.$addr.' db='.$db );
		}
		return $row;
	}
	if( $isLive ) {
		$expect = gethostbyname( 'db1.colo.printmotive.com' );
		if( $addr !== $expect || preg_match( '/test$/', $db ) ) {
			throw new Exception( 'Refusing live warehouse write: expected db1 live, got addr='.$addr.' db='.$db );
		}
		return $row;
	}
	throw new Exception( 'Set IS_DEV=1 (db2 test) or IS_LIVE=1 (db1) for warehouse pull' );
}

function ads_refuse_upload( $argv ) {
	if( in_array( '--upload', $argv, true ) ) {
		fwrite( STDERR, "Warehouse scripts never upload conversions.\n" );
		exit( 2 );
	}
}

/** FK order for dump/upsert. Natural keys only — no sequences. */
function ads_warehouse_tables() {
	return array(
		'ad_network',
		'ad_account',
		'ad_campaign',
		'ad_adgroup',
		'ad_ad',
		'ad_keyword',
		'ad_metrics_daily',
		'ad_user_attribution',
		'ad_order_attribution',
		'ad_api_secret',
	);
}

function ads_refuse_db1_load() {
	$host = function_exists( 'gethostname' ) ? gethostname() : php_uname( 'n' );
	if( preg_match( '/^(dev\d|devthumb|ux|sandbox)/i', $host ) ) {
		fwrite( STDERR, "Refusing warehouse load on a dev host. db1 is unreachable by design.\n" );
		fwrite( STDERR, "Dump from db2 here; apply the dump on a prod host after sign-off.\n" );
		exit( 2 );
	}
	if( !empty( $_SERVER['IS_DEV'] ) || !empty( $_SERVER['IS_SANDBOX'] ) ) {
		fwrite( STDERR, "Refusing warehouse load with IS_DEV/IS_SANDBOX set.\n" );
		exit( 2 );
	}
}

function ads_field( $arr, $camel, $snake = null ) {
	if( !is_array( $arr ) ) {
		return null;
	}
	if( array_key_exists( $camel, $arr ) && $arr[$camel] !== null && $arr[$camel] !== '' ) {
		return $arr[$camel];
	}
	if( $snake !== null && array_key_exists( $snake, $arr ) && $arr[$snake] !== null && $arr[$snake] !== '' ) {
		return $arr[$snake];
	}
	return null;
}

function ads_warehouse_schema_path() {
	if( defined( 'STATS_PKG_PATH' ) ) {
		return STATS_PKG_PATH.'admin/ad_warehouse_schema.sql';
	}
	return dirname( dirname( __FILE__ ) ).'/admin/ad_warehouse_schema.sql';
}

function ads_apply_warehouse_schema() {
	global $gBitSystem;
	$path = ads_warehouse_schema_path();
	if( !is_readable( $path ) ) {
		throw new Exception( 'Missing warehouse schema file: '.$path );
	}
	$n = 0;
	foreach( ads_sql_statements( $path ) as $stmt ) {
		$gBitSystem->mDb->query( $stmt );
		$n++;
	}
	return $n;
}

function ads_warehouse_table_exists( $pTable ) {
	global $gBitSystem;
	return (bool)$gBitSystem->mDb->getOne(
		"SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name=?",
		array( $pTable )
	);
}

function ads_sql_statements( $path ) {
	$raw = file_get_contents( $path );
	$kept = array();
	foreach( explode( "\n", $raw ) as $line ) {
		if( preg_match( '/^\s*--/', $line ) ) {
			continue;
		}
		$kept[] = $line;
	}
	$blob = implode( "\n", $kept );
	$out = array();
	foreach( explode( ';', $blob ) as $stmt ) {
		$stmt = trim( $stmt );
		if( $stmt !== '' ) {
			$out[] = $stmt;
		}
	}
	return $out;
}

function ads_upsert_touch( $table, $conflictCols, $insert, $updateCols ) {
	global $gBitSystem;
	foreach( $insert as $k => $v ) {
		if( is_bool( $v ) ) {
			$insert[$k] = $v ? 't' : 'f';
		}
	}
	$cols = array_keys( $insert );
	$placeholders = implode( ',', array_fill( 0, count( $cols ), '?' ) );
	$colSql = implode( ',', $cols );
	$conflict = implode( ',', $conflictCols );
	$sets = array();
	foreach( $updateCols as $c ) {
		$sets[] = $c.' = EXCLUDED.'.$c;
	}
	$sql = "INSERT INTO $table ($colSql) VALUES ($placeholders)
		ON CONFLICT ($conflict) DO UPDATE SET ".implode( ', ', $sets );
	$gBitSystem->mDb->query( $sql, array_values( $insert ) );
}

function ads_parse_landing_keys( $query, $url = null ) {
	$blob = $query;
	if( ( $blob === null || $blob === '' ) && $url && strpos( $url, '?' ) !== false ) {
		$blob = parse_url( $url, PHP_URL_QUERY );
	}
	$p = array();
	if( $blob ) {
		parse_str( $blob, $p );
	}
	$campaignId = null;
	if( !empty( $p['gad_campaignid'] ) && ctype_digit( (string)$p['gad_campaignid'] ) ) {
		$campaignId = (string)$p['gad_campaignid'];
	} elseif( !empty( $p['utm_campaign'] ) && ctype_digit( (string)$p['utm_campaign'] ) ) {
		$campaignId = (string)$p['utm_campaign'];
	}
	$network = null;
	$clickId = null;
	if( !empty( $p['gclid'] ) ) {
		$network = 'google';
		$clickId = $p['gclid'];
	} elseif( !empty( $p['msclkid'] ) ) {
		$network = 'microsoft';
		$clickId = $p['msclkid'];
	} elseif( !empty( $p['fbclid'] ) ) {
		$network = 'meta';
		$clickId = $p['fbclid'];
	} elseif( !empty( $p['ttclid'] ) ) {
		$network = 'tiktok';
		$clickId = $p['ttclid'];
	} elseif( !empty( $p['gad_campaignid'] ) || !empty( $p['gad_source'] ) || !empty( $p['gbraid'] ) || !empty( $p['wbraid'] ) ) {
		$network = 'google';
	} elseif( !empty( $p['utm_source'] ) ) {
		$src = strtolower( $p['utm_source'] );
		if( strpos( $src, 'google' ) !== false || $src === 'adwords' ) {
			$network = 'google';
		} elseif( strpos( $src, 'bing' ) !== false || strpos( $src, 'microsoft' ) !== false ) {
			$network = 'microsoft';
		} elseif( strpos( $src, 'facebook' ) !== false || strpos( $src, 'instagram' ) !== false || $src === 'fb' || $src === 'ig' ) {
			$network = 'meta';
		} elseif( strpos( $src, 'tiktok' ) !== false ) {
			$network = 'tiktok';
		}
	}
	if( $network === null ) {
		foreach( $p as $k => $v ) {
			if( strpos( $k, 'ctm_' ) === 0 ) {
				$network = 'google';
				break;
			}
		}
	}
	$untrackedPaid = ( $clickId !== null && ( empty( $p['ctm_campaign'] ) ) && $campaignId === null );
	return array(
		'network_code'  => $network,
		'campaign_id'   => $campaignId,
		'campaign_name' => !empty( $p['ctm_campaign'] ) ? $p['ctm_campaign'] : ( !empty( $p['utm_campaign'] ) && !ctype_digit( (string)$p['utm_campaign'] ) ? $p['utm_campaign'] : null ),
		'adgroup_id'    => !empty( $p['gad_adgroupid'] ) ? (string)$p['gad_adgroupid'] : null,
		'adgroup_name'  => !empty( $p['ctm_adgroup'] ) ? $p['ctm_adgroup'] : null,
		'keyword_id'    => null,
		'keyword_text'  => !empty( $p['ctm_term'] ) ? $p['ctm_term'] : ( !empty( $p['utm_term'] ) ? $p['utm_term'] : null ),
		'click_id'      => $clickId,
		'untracked_paid'=> $untrackedPaid,
	);
}
