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

function ads_require_warehouse_db( $pServer = array() ) {
	global $gBitSystem;
	$row = $gBitSystem->mDb->getRow( 'SELECT inet_server_addr() AS addr, current_database() AS db' );
	$db = isset( $row['db'] ) ? $row['db'] : '';
	$addr = isset( $row['addr'] ) ? $row['addr'] : '';
	$isDev = BitBase::getParameter( $pServer, 'IS_DEV' ) || getenv( 'IS_DEV' );
	$isLive = BitBase::getParameter( $pServer, 'IS_LIVE' ) || getenv( 'IS_LIVE' );
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
		'stats_prefs',
		'stats_ad_network',
		'stats_ad_account',
		'stats_ad_campaign',
		'stats_ad_adgroup',
		'stats_ad_ad',
		'stats_ad_keyword',
		'stats_ad_metrics_daily',
		'stats_ad_user_attribution',
		'stats_ad_order_attribution',
	);
}

function ads_warehouse_legacy_rename_map() {
	return array(
		'ad_network'            => 'stats_ad_network',
		'ad_account'            => 'stats_ad_account',
		'ad_campaign'           => 'stats_ad_campaign',
		'ad_adgroup'            => 'stats_ad_adgroup',
		'ad_ad'                 => 'stats_ad_ad',
		'ad_keyword'            => 'stats_ad_keyword',
		'ad_metrics_daily'      => 'stats_ad_metrics_daily',
		'ad_user_attribution'   => 'stats_ad_user_attribution',
		'ad_order_attribution'  => 'stats_ad_order_attribution',
	);
}

function ads_refuse_db1_load( $pServer = array() ) {
	$host = function_exists( 'gethostname' ) ? gethostname() : php_uname( 'n' );
	if( preg_match( '/^(dev\d|devthumb|ux|sandbox)/i', $host ) ) {
		fwrite( STDERR, "Refusing warehouse load on a dev host. db1 is unreachable by design.\n" );
		fwrite( STDERR, "Dump from db2 here; apply the dump on a prod host after sign-off.\n" );
		exit( 2 );
	}
	if( BitBase::getParameter( $pServer, 'IS_DEV' ) || BitBase::getParameter( $pServer, 'IS_SANDBOX' ) ) {
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
	$db = $gBitSystem->mDb;
	foreach( ads_warehouse_legacy_rename_map() as $old => $new ) {
		if( ads_warehouse_table_exists( $old ) && !ads_warehouse_table_exists( $new ) ) {
			$db->query( 'ALTER TABLE '.$old.' RENAME TO '.$new );
		}
	}
	if( ads_warehouse_table_exists( 'ad_api_secret' ) ) {
		$db->query(
			"CREATE TABLE IF NOT EXISTS stats_prefs (
				pref_name text PRIMARY KEY,
				pref_value text NOT NULL,
				updated_at timestamptz NOT NULL DEFAULT now()
			)"
		);
		$db->query(
			"INSERT INTO stats_prefs (pref_name, pref_value, updated_at)
			 SELECT secret_name, secret_value, updated_at FROM ad_api_secret
			 ON CONFLICT (pref_name) DO NOTHING"
		);
		$db->query( 'DROP TABLE ad_api_secret' );
	}
	$indexMap = array(
		'ad_campaign_name_idx'              => 'stats_ad_campaign_name_idx',
		'ad_metrics_daily_campaign_idx'     => 'stats_ad_metrics_daily_campaign_idx',
		'ad_user_attribution_campaign_idx'  => 'stats_ad_user_attribution_campaign_idx',
		'ad_user_attribution_name_idx'      => 'stats_ad_user_attribution_name_idx',
		'ad_order_attribution_campaign_idx' => 'stats_ad_order_attribution_campaign_idx',
		'ad_order_attribution_user_idx'     => 'stats_ad_order_attribution_user_idx',
		'ad_order_attribution_name_idx'     => 'stats_ad_order_attribution_name_idx',
	);
	foreach( $indexMap as $old => $new ) {
		$has = $db->getOne(
			"SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
			  WHERE n.nspname = 'public' AND c.relkind = 'i' AND c.relname = ?",
			array( $old )
		);
		$hasNew = $db->getOne(
			"SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
			  WHERE n.nspname = 'public' AND c.relkind = 'i' AND c.relname = ?",
			array( $new )
		);
		if( $has && !$hasNew ) {
			$db->query( 'ALTER INDEX '.$old.' RENAME TO '.$new );
		}
	}
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
