<?php
/**
 * Pull Google entity snapshots + daily metrics into the warehouse.
 * READ ONLY vs Google. Lives in stats so it deploys with the site.
 *
 *   php stats/admin/sh_ad_warehouse_pull.php --site_name=example --metrics=campaign
 *
 * Secrets: Ad API setup page (kernel_config). Refuses --upload.
 */
chdir( dirname( __FILE__ ) );
$gShellScript = true;
$gLightweightScan = true;
foreach( array( 'IS_DEV', 'IS_LIVE', 'IS_SANDBOX', 'SITE_NAME' ) as $k ) {
	$v = getenv( $k );
	if( $v !== false && $v !== '' && empty( $_SERVER[$k] ) ) {
		$_SERVER[$k] = $v;
	}
}
require_once dirname( __FILE__ ).'/../../config/kernel/cron_setup_inc.php';
require_once STATS_PKG_INCLUDE_PATH.'ads_api_lib.php';
require_once STATS_PKG_INCLUDE_PATH.'ads_warehouse_lib.php';
ads_refuse_upload( $argv );

$since = date( 'Y-m-d', strtotime( '-90 days' ) );
$until = date( 'Y-m-d' );
$doEntities = true;
$doMigrate = false;
$doApply = true;
$metrics = array( 'campaign' );
foreach( $argv as $arg ) {
	if( strpos( $arg, '--since=' ) === 0 ) {
		$since = substr( $arg, 8 );
	} elseif( strpos( $arg, '--until=' ) === 0 ) {
		$until = substr( $arg, 8 );
	} elseif( $arg === '--full' ) {
		$since = '2020-01-01';
	} elseif( $arg === '--entities-only' ) {
		$doMigrate = false;
		$metrics = array();
	} elseif( $arg === '--migrate-spend' ) {
		$doMigrate = true;
	} elseif( $arg === '--no-apply' ) {
		$doApply = false;
	} elseif( $arg === '--metrics-only' ) {
		$doEntities = false;
	} elseif( strpos( $arg, '--metrics=' ) === 0 ) {
		$metrics = array_filter( explode( ',', substr( $arg, 10 ) ) );
	}
}

$row = ads_require_warehouse_db( $_SERVER );
fwrite( STDERR, "warehouse pull on {$row['addr']} {$row['db']}\n" );
if( $doApply ) {
	$n = ads_apply_warehouse_schema();
	fwrite( STDERR, "applied warehouse schema ($n statements)\n" );
}

$cid = ads_customer_id();
if( !$cid ) {
	fwrite( STDERR, "Set google_ads_customer_id on Ad API setup (digits only).\n" );
	exit( 1 );
}
$network = 'google';
$db = $gBitSystem->mDb;

function warehouse_upsert_account( $network, $cid, $fields ) {
	ads_upsert_touch(
		'stats_ad_account',
		array( 'network_code', 'account_id' ),
		array(
			'network_code' => $network,
			'account_id'   => (string)$cid,
			'account_name' => isset( $fields['name'] ) ? $fields['name'] : null,
			'is_manager'   => false,
			'status'       => isset( $fields['status'] ) ? $fields['status'] : null,
			'currency'     => isset( $fields['currency'] ) ? $fields['currency'] : 'USD',
			'timezone'     => isset( $fields['timezone'] ) ? $fields['timezone'] : null,
			'extra'        => '{}',
			'first_seen_at'=> date( 'c' ),
			'last_seen_at' => date( 'c' ),
		),
		array( 'account_name', 'status', 'currency', 'timezone', 'last_seen_at' )
	);
}

function warehouse_upsert_campaign( $network, $cid, $camp ) {
	ads_upsert_touch(
		'stats_ad_campaign',
		array( 'network_code', 'account_id', 'campaign_id' ),
		array(
			'network_code'     => $network,
			'account_id'       => (string)$cid,
			'campaign_id'      => (string)$camp['id'],
			'campaign_name'    => isset( $camp['name'] ) ? $camp['name'] : null,
			'channel'          => isset( $camp['channel'] ) ? $camp['channel'] : null,
			'status'           => isset( $camp['status'] ) ? $camp['status'] : null,
			'bidding_strategy' => isset( $camp['bidding'] ) ? $camp['bidding'] : null,
			'target_roas'      => isset( $camp['target_roas'] ) ? $camp['target_roas'] : null,
			'extra'            => isset( $camp['extra'] ) ? json_encode( $camp['extra'] ) : '{}',
			'first_seen_at'    => date( 'c' ),
			'last_seen_at'     => date( 'c' ),
		),
		array( 'campaign_name', 'channel', 'status', 'bidding_strategy', 'target_roas', 'extra', 'last_seen_at' )
	);
}

function warehouse_flush_metrics( &$batch ) {
	global $gBitSystem;
	if( empty( $batch ) ) {
		return;
	}
	$cols = array(
		'metric_date', 'network_code', 'account_id', 'grain', 'campaign_id',
		'adgroup_id', 'ad_id', 'keyword_id', 'spend', 'clicks', 'impressions',
		'network_conversions', 'network_value', 'currency', 'extra', 'pulled_at',
	);
	$now = date( 'c' );
	$values = array();
	$binds = array();
	foreach( $batch as $row ) {
		$values[] = '('.implode( ',', array_fill( 0, count( $cols ), '?' ) ).')';
		$binds[] = $row['metric_date'];
		$binds[] = $row['network_code'];
		$binds[] = $row['account_id'];
		$binds[] = $row['grain'];
		$binds[] = (string)$row['campaign_id'];
		$binds[] = isset( $row['adgroup_id'] ) ? (string)$row['adgroup_id'] : '';
		$binds[] = isset( $row['ad_id'] ) ? (string)$row['ad_id'] : '';
		$binds[] = isset( $row['keyword_id'] ) ? (string)$row['keyword_id'] : '';
		$binds[] = $row['spend'];
		$binds[] = $row['clicks'];
		$binds[] = $row['impressions'];
		$binds[] = $row['network_conversions'];
		$binds[] = $row['network_value'];
		$binds[] = isset( $row['currency'] ) ? $row['currency'] : 'USD';
		$binds[] = isset( $row['extra'] ) ? json_encode( $row['extra'] ) : '{}';
		$binds[] = $now;
	}
	$sql = 'INSERT INTO stats_ad_metrics_daily ('.implode( ',', $cols ).') VALUES '.implode( ',', $values ).'
		ON CONFLICT (metric_date, network_code, account_id, grain, campaign_id, adgroup_id, ad_id, keyword_id) DO UPDATE SET
		  spend = EXCLUDED.spend,
		  clicks = EXCLUDED.clicks,
		  impressions = EXCLUDED.impressions,
		  network_conversions = EXCLUDED.network_conversions,
		  network_value = EXCLUDED.network_value,
		  currency = EXCLUDED.currency,
		  extra = EXCLUDED.extra,
		  pulled_at = EXCLUDED.pulled_at';
	$gBitSystem->mDb->query( $sql, $binds );
	$batch = array();
}

function warehouse_metrics_from_api_row( $r, $network, $cid, $grain ) {
	$camp = isset( $r['campaign'] ) ? $r['campaign'] : array();
	$m = isset( $r['metrics'] ) ? $r['metrics'] : array();
	$seg = isset( $r['segments'] ) ? $r['segments'] : array();
	$cost = ads_field( $m, 'costMicros', 'cost_micros' );
	if( $cost === null ) {
		$cost = 0;
	}
	$ag = isset( $r['adGroup'] ) ? $r['adGroup'] : ( isset( $r['ad_group'] ) ? $r['ad_group'] : array() );
	$crit = isset( $r['adGroupCriterion'] ) ? $r['adGroupCriterion'] : ( isset( $r['ad_group_criterion'] ) ? $r['ad_group_criterion'] : array() );
	$adWrap = isset( $r['adGroupAd'] ) ? $r['adGroupAd'] : ( isset( $r['ad_group_ad'] ) ? $r['ad_group_ad'] : array() );
	$ad = isset( $adWrap['ad'] ) ? $adWrap['ad'] : array();
	return array(
		'metric_date'         => ads_field( $seg, 'date' ),
		'network_code'        => $network,
		'account_id'          => (string)$cid,
		'grain'               => $grain,
		'campaign_id'         => (string)ads_field( $camp, 'id' ),
		'adgroup_id'          => $grain === 'campaign' ? '' : (string)ads_field( $ag, 'id' ),
		'ad_id'               => $grain === 'ad' ? (string)ads_field( $ad, 'id' ) : '',
		'keyword_id'          => $grain === 'keyword' ? (string)ads_field( $crit, 'criterionId', 'criterion_id' ) : '',
		'spend'               => round( ((float)$cost) / 1000000.0, 4 ),
		'clicks'              => (int)( ads_field( $m, 'clicks' ) ?: 0 ),
		'impressions'         => (int)( ads_field( $m, 'impressions' ) ?: 0 ),
		'network_conversions' => ads_field( $m, 'conversions' ),
		'network_value'       => ads_field( $m, 'conversionsValue', 'conversions_value' ),
		'currency'            => 'USD',
		'extra'               => array( 'cost_micros' => (int)$cost, 'source' => 'google_ads_api' ),
	);
}

if( $doMigrate && !ads_warehouse_table_exists( 'ads_spend_daily' ) ) {
	fwrite( STDERR, "skip migrate: ads_spend_daily not on this database\n" );
	$doMigrate = false;
}

if( $doMigrate ) {
	fwrite( STDERR, "migrate ads_spend_daily -> stats_ad_metrics_daily grain=campaign\n" );
	$db->query(
		"INSERT INTO stats_ad_account (network_code, account_id, account_name, is_manager, currency, first_seen_at, last_seen_at)
		 SELECT 'google', customer_id::text, 'Presto Photo', false, 'USD', now(), now()
		 FROM ads_spend_daily GROUP BY customer_id
		 ON CONFLICT (network_code, account_id) DO UPDATE SET last_seen_at = now()"
	);
	$db->query(
		"INSERT INTO stats_ad_campaign (network_code, account_id, campaign_id, campaign_name, channel, status, first_seen_at, last_seen_at)
		 SELECT 'google', customer_id::text, campaign_id::text, MIN(campaign_name), MIN(channel), MIN(campaign_status), now(), now()
		 FROM ads_spend_daily GROUP BY customer_id, campaign_id
		 ON CONFLICT (network_code, account_id, campaign_id) DO UPDATE SET
		   campaign_name = COALESCE(EXCLUDED.campaign_name, stats_ad_campaign.campaign_name),
		   channel = COALESCE(EXCLUDED.channel, stats_ad_campaign.channel),
		   status = COALESCE(EXCLUDED.status, stats_ad_campaign.status),
		   last_seen_at = now()"
	);
	$mig = $db->query(
		"INSERT INTO stats_ad_metrics_daily (
			metric_date, network_code, account_id, grain, campaign_id, adgroup_id, ad_id, keyword_id,
			spend, clicks, impressions, network_conversions, network_value, currency, extra, pulled_at
		 )
		 SELECT spend_date, 'google', customer_id::text, 'campaign', campaign_id::text, '', '', '',
			ROUND((cost_micros::numeric)/1000000.0, 4), clicks, impressions, conversions, conversions_value, 'USD',
			jsonb_build_object('cost_micros', cost_micros, 'source', 'ads_spend_daily'), pulled_at
		 FROM ads_spend_daily
		 ON CONFLICT (metric_date, network_code, account_id, grain, campaign_id, adgroup_id, ad_id, keyword_id) DO UPDATE SET
		   spend = EXCLUDED.spend,
		   clicks = EXCLUDED.clicks,
		   impressions = EXCLUDED.impressions,
		   network_conversions = EXCLUDED.network_conversions,
		   network_value = EXCLUDED.network_value,
		   extra = EXCLUDED.extra,
		   pulled_at = EXCLUDED.pulled_at"
	);
	$n = $db->getOne( "SELECT COUNT(*) FROM stats_ad_metrics_daily WHERE grain='campaign' AND extra->>'source'='ads_spend_daily'" );
	fwrite( STDERR, "campaign-day rows from ads_spend_daily: $n\n" );
}

$token = null;
if( $doEntities || $metrics ) {
	$token = ads_access_token();
	fwrite( STDERR, "got Ads token, customer $cid\n" );
}

if( $doEntities ) {
	fwrite( STDERR, "pull customer\n" );
	$custRows = ads_search_stream( $token, $cid, 'SELECT customer.id, customer.descriptive_name, customer.currency_code, customer.time_zone, customer.status FROM customer LIMIT 1' );
	$c0 = isset( $custRows[0]['customer'] ) ? $custRows[0]['customer'] : array();
	warehouse_upsert_account( $network, $cid, array(
		'name'     => ads_field( $c0, 'descriptiveName', 'descriptive_name' ),
		'currency' => ads_field( $c0, 'currencyCode', 'currency_code' ),
		'timezone' => ads_field( $c0, 'timeZone', 'time_zone' ),
		'status'   => ads_field( $c0, 'status' ),
	) );

	fwrite( STDERR, "pull campaigns\n" );
	$gaql = 'SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type, campaign.bidding_strategy_type, campaign.maximize_conversion_value.target_roas, campaign.target_roas.target_roas FROM campaign';
	$n = 0;
	foreach( ads_search_stream( $token, $cid, $gaql ) as $r ) {
		$c = isset( $r['campaign'] ) ? $r['campaign'] : array();
		$mcv = ads_field( $c, 'maximizeConversionValue', 'maximize_conversion_value' );
		$troasObj = ads_field( $c, 'targetRoas', 'target_roas' );
		$troas = null;
		if( is_array( $mcv ) ) {
			$troas = ads_field( $mcv, 'targetRoas', 'target_roas' );
		}
		if( $troas === null && is_array( $troasObj ) ) {
			$troas = ads_field( $troasObj, 'targetRoas', 'target_roas' );
		} elseif( $troas === null && is_numeric( $troasObj ) ) {
			$troas = $troasObj;
		}
		warehouse_upsert_campaign( $network, $cid, array(
			'id'          => ads_field( $c, 'id' ),
			'name'        => ads_field( $c, 'name' ),
			'channel'     => ads_field( $c, 'advertisingChannelType', 'advertising_channel_type' ),
			'status'      => ads_field( $c, 'status' ),
			'bidding'     => ads_field( $c, 'biddingStrategyType', 'bidding_strategy_type' ),
			'target_roas' => $troas,
			'extra'       => array(),
		) );
		$n++;
	}
	fwrite( STDERR, "upserted $n campaigns\n" );

	fwrite( STDERR, "pull ad groups\n" );
	$n = 0;
	$gaql = 'SELECT campaign.id, ad_group.id, ad_group.name, ad_group.status, ad_group.type FROM ad_group';
	foreach( ads_search_stream( $token, $cid, $gaql ) as $r ) {
		$c = isset( $r['campaign'] ) ? $r['campaign'] : array();
		$ag = isset( $r['adGroup'] ) ? $r['adGroup'] : ( isset( $r['ad_group'] ) ? $r['ad_group'] : array() );
		$campId = ads_field( $c, 'id' );
		$agId = ads_field( $ag, 'id' );
		if( $campId === null || $agId === null ) {
			continue;
		}
		$exists = $db->getOne(
			'SELECT 1 FROM stats_ad_campaign WHERE network_code=? AND account_id=? AND campaign_id=?',
			array( $network, (string)$cid, (string)$campId )
		);
		if( !$exists ) {
			warehouse_upsert_campaign( $network, $cid, array( 'id' => $campId, 'name' => null ) );
		}
		ads_upsert_touch(
			'stats_ad_adgroup',
			array( 'network_code', 'account_id', 'campaign_id', 'adgroup_id' ),
			array(
				'network_code'  => $network,
				'account_id'    => (string)$cid,
				'campaign_id'   => (string)$campId,
				'adgroup_id'    => (string)$agId,
				'adgroup_name'  => ads_field( $ag, 'name' ),
				'adgroup_kind'  => 'adgroup',
				'status'        => ads_field( $ag, 'status' ),
				'extra'         => json_encode( array( 'type' => ads_field( $ag, 'type' ) ) ),
				'first_seen_at' => date( 'c' ),
				'last_seen_at'  => date( 'c' ),
			),
			array( 'adgroup_name', 'status', 'extra', 'last_seen_at' )
		);
		$n++;
	}
	fwrite( STDERR, "upserted $n ad groups\n" );

	fwrite( STDERR, "pull asset groups (PMax)\n" );
	$n = 0;
	try {
		$gaql = 'SELECT campaign.id, asset_group.id, asset_group.name, asset_group.status FROM asset_group';
		foreach( ads_search_stream( $token, $cid, $gaql ) as $r ) {
			$c = isset( $r['campaign'] ) ? $r['campaign'] : array();
			$ag = isset( $r['assetGroup'] ) ? $r['assetGroup'] : ( isset( $r['asset_group'] ) ? $r['asset_group'] : array() );
			$campId = ads_field( $c, 'id' );
			$agId = ads_field( $ag, 'id' );
			if( $campId === null || $agId === null ) {
				continue;
			}
			$exists = $db->getOne(
				'SELECT 1 FROM stats_ad_campaign WHERE network_code=? AND account_id=? AND campaign_id=?',
				array( $network, (string)$cid, (string)$campId )
			);
			if( !$exists ) {
				warehouse_upsert_campaign( $network, $cid, array( 'id' => $campId ) );
			}
			ads_upsert_touch(
				'stats_ad_adgroup',
				array( 'network_code', 'account_id', 'campaign_id', 'adgroup_id' ),
				array(
					'network_code'  => $network,
					'account_id'    => (string)$cid,
					'campaign_id'   => (string)$campId,
					'adgroup_id'    => (string)$agId,
					'adgroup_name'  => ads_field( $ag, 'name' ),
					'adgroup_kind'  => 'asset_group',
					'status'        => ads_field( $ag, 'status' ),
					'extra'         => json_encode( array( 'kind' => 'asset_group' ) ),
					'first_seen_at' => date( 'c' ),
					'last_seen_at'  => date( 'c' ),
				),
				array( 'adgroup_name', 'adgroup_kind', 'status', 'extra', 'last_seen_at' )
			);
			$n++;
		}
		fwrite( STDERR, "upserted $n asset groups\n" );
	} catch( Exception $e ) {
		fwrite( STDERR, "asset_group pull skipped: ".$e->getMessage()."\n" );
	}

	fwrite( STDERR, "pull ads\n" );
	$n = 0;
	$gaql = 'SELECT campaign.id, ad_group.id, ad_group_ad.ad.id, ad_group_ad.status, ad_group_ad.ad.type, ad_group_ad.ad.name FROM ad_group_ad';
	foreach( ads_search_stream( $token, $cid, $gaql ) as $r ) {
		$c = isset( $r['campaign'] ) ? $r['campaign'] : array();
		$ag = isset( $r['adGroup'] ) ? $r['adGroup'] : ( isset( $r['ad_group'] ) ? $r['ad_group'] : array() );
		$wrap = isset( $r['adGroupAd'] ) ? $r['adGroupAd'] : ( isset( $r['ad_group_ad'] ) ? $r['ad_group_ad'] : array() );
		$ad = isset( $wrap['ad'] ) ? $wrap['ad'] : array();
		$campId = ads_field( $c, 'id' );
		$agId = ads_field( $ag, 'id' );
		$adId = ads_field( $ad, 'id' );
		if( $campId === null || $adId === null ) {
			continue;
		}
		$exists = $db->getOne(
			'SELECT 1 FROM stats_ad_campaign WHERE network_code=? AND account_id=? AND campaign_id=?',
			array( $network, (string)$cid, (string)$campId )
		);
		if( !$exists ) {
			warehouse_upsert_campaign( $network, $cid, array( 'id' => $campId ) );
		}
		ads_upsert_touch(
			'stats_ad_ad',
			array( 'network_code', 'account_id', 'campaign_id', 'adgroup_id', 'ad_id' ),
			array(
				'network_code'  => $network,
				'account_id'    => (string)$cid,
				'campaign_id'   => (string)$campId,
				'adgroup_id'    => $agId === null ? '' : (string)$agId,
				'ad_id'         => (string)$adId,
				'ad_name'       => ads_field( $ad, 'name' ),
				'ad_type'       => ads_field( $ad, 'type' ),
				'status'        => ads_field( $wrap, 'status' ),
				'extra'         => '{}',
				'first_seen_at' => date( 'c' ),
				'last_seen_at'  => date( 'c' ),
			),
			array( 'ad_name', 'ad_type', 'status', 'last_seen_at' )
		);
		$n++;
	}
	fwrite( STDERR, "upserted $n ads\n" );

	fwrite( STDERR, "pull keywords\n" );
	$n = 0;
	$skipped = 0;
	$gaql = "SELECT campaign.id, ad_group.id, ad_group_criterion.criterion_id, ad_group_criterion.status, ad_group_criterion.keyword.text, ad_group_criterion.keyword.match_type FROM ad_group_criterion WHERE ad_group_criterion.type = 'KEYWORD'";
	foreach( ads_search_stream( $token, $cid, $gaql ) as $r ) {
		$c = isset( $r['campaign'] ) ? $r['campaign'] : array();
		$ag = isset( $r['adGroup'] ) ? $r['adGroup'] : ( isset( $r['ad_group'] ) ? $r['ad_group'] : array() );
		$crit = isset( $r['adGroupCriterion'] ) ? $r['adGroupCriterion'] : ( isset( $r['ad_group_criterion'] ) ? $r['ad_group_criterion'] : array() );
		$kw = ads_field( $crit, 'keyword' );
		if( !is_array( $kw ) ) {
			$kw = array();
		}
		$campId = ads_field( $c, 'id' );
		$agId = ads_field( $ag, 'id' );
		$kwId = ads_field( $crit, 'criterionId', 'criterion_id' );
		if( $campId === null || $agId === null || $kwId === null ) {
			continue;
		}
		$agExists = $db->getOne(
			'SELECT 1 FROM stats_ad_adgroup WHERE network_code=? AND account_id=? AND campaign_id=? AND adgroup_id=?',
			array( $network, (string)$cid, (string)$campId, (string)$agId )
		);
		if( !$agExists ) {
			$skipped++;
			continue;
		}
		ads_upsert_touch(
			'stats_ad_keyword',
			array( 'network_code', 'account_id', 'campaign_id', 'adgroup_id', 'keyword_id' ),
			array(
				'network_code'  => $network,
				'account_id'    => (string)$cid,
				'campaign_id'   => (string)$campId,
				'adgroup_id'    => (string)$agId,
				'keyword_id'    => (string)$kwId,
				'keyword_text'  => ads_field( $kw, 'text' ),
				'match_type'    => ads_field( $kw, 'matchType', 'match_type' ),
				'status'        => ads_field( $crit, 'status' ),
				'extra'         => '{}',
				'first_seen_at' => date( 'c' ),
				'last_seen_at'  => date( 'c' ),
			),
			array( 'keyword_text', 'match_type', 'status', 'last_seen_at' )
		);
		$n++;
	}
	fwrite( STDERR, "upserted $n keywords (skipped $skipped without ad group)\n" );
}

$metricGaql = array(
	'campaign' => "SELECT segments.date, campaign.id, metrics.cost_micros, metrics.clicks, metrics.impressions, metrics.conversions, metrics.conversions_value FROM campaign WHERE segments.date BETWEEN '%s' AND '%s'",
	'adgroup'  => "SELECT segments.date, campaign.id, ad_group.id, metrics.cost_micros, metrics.clicks, metrics.impressions, metrics.conversions, metrics.conversions_value FROM ad_group WHERE segments.date BETWEEN '%s' AND '%s'",
	'keyword'  => "SELECT segments.date, campaign.id, ad_group.id, ad_group_criterion.criterion_id, metrics.cost_micros, metrics.clicks, metrics.impressions, metrics.conversions, metrics.conversions_value FROM keyword_view WHERE segments.date BETWEEN '%s' AND '%s'",
);

foreach( $metrics as $grain ) {
	if( empty( $metricGaql[$grain] ) ) {
		fwrite( STDERR, "unknown grain $grain\n" );
		continue;
	}
	$gaql = sprintf( $metricGaql[$grain], $since, $until );
	fwrite( STDERR, "pull metrics grain=$grain $since..$until\n" );
	$n = 0;
	$batch = array();
	$db->StartTrans();
	ads_search_each( $token, $cid, $gaql, function( $r ) use ( &$batch, &$n, $network, $cid, $grain ) {
		$batch[] = warehouse_metrics_from_api_row( $r, $network, $cid, $grain );
		$n++;
		if( count( $batch ) >= 400 ) {
			warehouse_flush_metrics( $batch );
		}
		if( $n % 5000 === 0 ) {
			fwrite( STDERR, "  $n rows\n" );
		}
	} );
	warehouse_flush_metrics( $batch );
	$db->CompleteTrans();
	fwrite( STDERR, "upserted $n $grain-day rows\n" );
}

$summary = $db->getAll(
	"SELECT 'network' AS k, COUNT(*)::text AS n FROM stats_ad_network
	 UNION ALL SELECT 'account', COUNT(*)::text FROM stats_ad_account
	 UNION ALL SELECT 'campaign', COUNT(*)::text FROM stats_ad_campaign
	 UNION ALL SELECT 'adgroup', COUNT(*)::text FROM stats_ad_adgroup
	 UNION ALL SELECT 'ad', COUNT(*)::text FROM stats_ad_ad
	 UNION ALL SELECT 'keyword', COUNT(*)::text FROM stats_ad_keyword
	 UNION ALL SELECT 'metrics_campaign', COUNT(*)::text FROM stats_ad_metrics_daily WHERE grain='campaign'
	 UNION ALL SELECT 'metrics_adgroup', COUNT(*)::text FROM stats_ad_metrics_daily WHERE grain='adgroup'
	 UNION ALL SELECT 'metrics_keyword', COUNT(*)::text FROM stats_ad_metrics_daily WHERE grain='keyword'"
);
foreach( $summary as $s ) {
	fwrite( STDERR, $s['k'].'='.$s['n']."\n" );
}
