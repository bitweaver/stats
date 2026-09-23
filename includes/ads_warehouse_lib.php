<?php
/**
 * Shared warehouse helpers. No Google conversion uploads.
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
	$isDev = BitBase::getParameter( $pServer, 'IS_DEV' ) || getenv( 'IS_DEV' );
	if( $isDev ) {
		if( !preg_match( '/test$/', $db ) ) {
			throw new Exception( 'IS_DEV is set but database name does not end in test: '.$db );
		}
		return $row;
	}
	if( preg_match( '/test$/', $db ) ) {
		throw new Exception( 'Live warehouse CLI against a test database: '.$db );
	}
	return $row;
}

function ads_refuse_upload( $argv ) {
	if( in_array( '--upload', $argv, true ) ) {
		fwrite( STDERR, "Warehouse scripts never upload conversions.\n" );
		exit( 2 );
	}
}

function ads_cli_run( $script, $extra = array() ) {
	$php = ( defined( 'PHP_BINARY' ) && PHP_BINARY ) ? PHP_BINARY : 'php';
	$cmd = escapeshellcmd( $php ).' '.escapeshellarg( $script );
	foreach( $extra as $a ) {
		$cmd .= ' '.escapeshellarg( $a );
	}
	fwrite( STDERR, "+ $cmd\n" );
	passthru( $cmd, $code );
	if( $code !== 0 ) {
		fwrite( STDERR, "failed $script exit $code\n" );
		exit( $code );
	}
}

/**
 * Drop derived warehouse rows. Keeps stats_prefs and stats_ad_network
 * (credentials and click-window). Google entities/metrics are pulled again.
 */
function ads_wipe_warehouse_derived() {
	global $gBitSystem;
	$db = $gBitSystem->mDb;
	$db->StartTrans();
	foreach( array(
		'stats_ad_order_attribution',
		'stats_ad_user_attribution',
		'stats_ad_metrics_daily',
	) as $t ) {
		if( ads_warehouse_table_exists( $t ) ) {
			$db->query( 'TRUNCATE TABLE '.$t );
		}
	}
	foreach( array(
		'stats_ad_ad',
		'stats_ad_keyword',
		'stats_ad_adgroup',
		'stats_ad_campaign',
		'stats_ad_account',
	) as $t ) {
		if( ads_warehouse_table_exists( $t ) ) {
			$db->query( 'DELETE FROM '.$t );
		}
	}
	$db->CompleteTrans();
}

/**
 * Empty first-touch tables. Keeps warehouse prefs/network. Referer host
 * counters (stats_referers) and pageviews are left alone.
 */
function ads_wipe_first_touch() {
	global $gBitSystem;
	$db = $gBitSystem->mDb;
	$db->StartTrans();
	foreach( array(
		'stats_referer_users_map',
		'stats_landing_urls',
		'stats_referer_urls',
	) as $t ) {
		$db->query( 'TRUNCATE TABLE '.$t.' RESTART IDENTITY CASCADE' );
	}
	$db->CompleteTrans();
}

function ads_clear_first_touch_map( $pSince, $pUntil ) {
	global $gBitSystem;
	$db = $gBitSystem->mDb;
	$n = $db->getOne(
		"SELECT COUNT(*) FROM stats_referer_users_map m
		 JOIN users_users u ON u.user_id = m.user_id
		 WHERE to_timestamp(u.registration_date) >= ?::timestamp
		   AND to_timestamp(u.registration_date) < (?::date + 1)",
		array( $pSince, $pUntil )
	);
	$db->query(
		"DELETE FROM stats_referer_users_map m
		 USING users_users u
		 WHERE m.user_id = u.user_id
		   AND to_timestamp(u.registration_date) >= ?::timestamp
		   AND to_timestamp(u.registration_date) < (?::date + 1)",
		array( $pSince, $pUntil )
	);
	return (int)$n;
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

function ads_google_campaign_lookup() {
	global $gBitSystem;
	static $byId = null;
	static $byName = null;
	if( $byId !== null ) {
		return array( $byId, $byName );
	}
	$byId = array();
	$byName = array();
	if( empty( $gBitSystem->mDb ) || !ads_warehouse_table_exists( 'stats_ad_campaign' ) ) {
		return array( $byId, $byName );
	}
	$rows = $gBitSystem->mDb->getAll(
		"SELECT campaign_id, campaign_name FROM stats_ad_campaign WHERE network_code = ?",
		array( 'google' )
	);
	foreach( $rows as $row ) {
		$id = (string)$row['campaign_id'];
		$name = $row['campaign_name'];
		$byId[$id] = $name;
		if( $name === null || $name === '' ) {
			continue;
		}
		$lk = strtolower( $name );
		if( isset( $byName[$lk] ) ) {
			$byName[$lk] = false;
		} else {
			$byName[$lk] = $id;
		}
	}
	return array( $byId, $byName );
}

function ads_parse_landing_keys( $query, $url = null ) {
	$blob = $query;
	if( ( $blob === null || $blob === '' ) && $url ) {
		$qpos = strpos( $url, '?' );
		if( $qpos !== false ) {
			$blob = substr( $url, $qpos + 1 );
		} elseif( preg_match( '/(?:^|[?&])(gclid|msclkid|gad_|gbraid|wbraid|utm_|ctm_)/i', $url ) ) {
			$blob = $url;
		}
	}
	$p = array();
	if( $blob ) {
		parse_str( $blob, $p );
	}
	list( $campById, $campByName ) = ads_google_campaign_lookup();
	$campaignId = null;
	$campaignName = null;
	foreach( array( 'utm_campaign', 'gad_campaignid' ) as $k ) {
		$id = !empty( $p[$k] ) ? (string)$p[$k] : '';
		if( $id !== '' && ctype_digit( $id ) && isset( $campById[$id] ) ) {
			$campaignId = $id;
			$campaignName = $campById[$id];
			break;
		}
	}
	$ctmName = !empty( $p['ctm_campaign'] ) ? trim( $p['ctm_campaign'] ) : '';
	if( $campaignId === null && $ctmName !== '' ) {
		$lk = strtolower( $ctmName );
		if( !empty( $campByName[$lk] ) ) {
			$campaignId = $campByName[$lk];
			$campaignName = $campById[$campaignId];
		} else {
			$campaignName = $ctmName;
		}
	} elseif( $campaignName === null && $ctmName !== '' ) {
		$campaignName = $ctmName;
	} elseif( $campaignName === null && !empty( $p['utm_campaign'] ) && !ctype_digit( (string)$p['utm_campaign'] ) ) {
		$campaignName = $p['utm_campaign'];
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
	$untrackedPaid = ( $clickId !== null && $campaignId === null && $ctmName === '' );
	return array(
		'network_code'  => $network,
		'campaign_id'   => $campaignId,
		'campaign_name' => $campaignName,
		'adgroup_id'    => !empty( $p['gad_adgroupid'] ) ? (string)$p['gad_adgroupid'] : null,
		'adgroup_name'  => !empty( $p['ctm_adgroup'] ) ? $p['ctm_adgroup'] : null,
		'keyword_id'    => null,
		'keyword_text'  => !empty( $p['ctm_term'] ) ? $p['ctm_term'] : ( !empty( $p['utm_term'] ) ? $p['utm_term'] : null ),
		'click_id'      => $clickId,
		'untracked_paid'=> $untrackedPaid,
	);
}
