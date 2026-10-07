<?php
/**
 * Pull ad network entity snapshots + daily metrics into the warehouse.
 * READ ONLY vs the network. Lives in stats so it deploys with the site.
 *
 *   php stats/admin/sh_ad_warehouse_pull.php --site_name=example --metrics=campaign,adgroup --conversions --clicks
 *
 * Flags:
 *   --since=Y-m-d --until=Y-m-d  metric window (default: last 90 days)
 *   --full                       since 2020-01-01
 *   --entities-only              entities, settings snapshot, conversion actions; no metrics
 *   --metrics-only               skip entities
 *   --metrics=campaign,adgroup   metric grains (campaign|adgroup|keyword); --metrics= for none
 *   --conversions                per conversion-action daily rows (stats_ad_conversion_daily)
 *   --clicks                     click ids per day (stats_ad_click); the network keeps 90 days
 *   --fields-check               print GAQL field compatibility for the API version in use, then exit
 *   --migrate-spend              one-time import of a legacy campaign/day spend table
 *   --no-apply                   skip the idempotent schema apply
 *
 * Secrets: Ad API setup page (stats_prefs). Refuses --upload.
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
$doConversions = false;
$doClicks = false;
$doFieldsCheck = false;
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
		$doConversions = false;
		$doClicks = false;
	} elseif( $arg === '--migrate-spend' ) {
		$doMigrate = true;
	} elseif( $arg === '--no-apply' ) {
		$doApply = false;
	} elseif( $arg === '--metrics-only' ) {
		$doEntities = false;
	} elseif( strpos( $arg, '--metrics=' ) === 0 ) {
		$metrics = array_filter( explode( ',', substr( $arg, 10 ) ) );
	} elseif( $arg === '--conversions' ) {
		$doConversions = true;
	} elseif( $arg === '--clicks' ) {
		$doClicks = true;
	} elseif( $arg === '--fields-check' ) {
		$doFieldsCheck = true;
	}
}
foreach( array( $since, $until ) as $d ) {
	if( !preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
		fwrite( STDERR, "Dates must be Y-m-d: $d\n" );
		exit( 1 );
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

// ---------------------------------------------------------------- GAQL

$baseMetrics = 'metrics.cost_micros, metrics.clicks, metrics.impressions';
$convMetricsBasic = 'metrics.conversions, metrics.conversions_value';
$convMetricsFull = $convMetricsBasic.', metrics.all_conversions, metrics.all_conversions_value, metrics.conversions_by_conversion_date, metrics.conversions_value_by_conversion_date';
$isMetrics = 'metrics.search_impression_share, metrics.search_budget_lost_impression_share, metrics.search_rank_lost_impression_share';

$metricGaql = array(
	'campaign' => "SELECT segments.date, campaign.id, $baseMetrics, $convMetricsFull FROM campaign WHERE segments.date BETWEEN '%s' AND '%s'",
	'adgroup'  => "SELECT segments.date, campaign.id, ad_group.id, $baseMetrics, $convMetricsFull FROM ad_group WHERE segments.date BETWEEN '%s' AND '%s'",
	'keyword'  => "SELECT segments.date, campaign.id, ad_group.id, ad_group_criterion.criterion_id, $baseMetrics, $convMetricsFull FROM keyword_view WHERE segments.date BETWEEN '%s' AND '%s'",
);
$metricGaqlBasic = array(
	'campaign' => "SELECT segments.date, campaign.id, $baseMetrics, $convMetricsBasic FROM campaign WHERE segments.date BETWEEN '%s' AND '%s'",
	'adgroup'  => "SELECT segments.date, campaign.id, ad_group.id, $baseMetrics, $convMetricsBasic FROM ad_group WHERE segments.date BETWEEN '%s' AND '%s'",
	'keyword'  => "SELECT segments.date, campaign.id, ad_group.id, ad_group_criterion.criterion_id, $baseMetrics, $convMetricsBasic FROM keyword_view WHERE segments.date BETWEEN '%s' AND '%s'",
);
$isGaql = "SELECT segments.date, campaign.id, metrics.impressions, $isMetrics FROM campaign WHERE segments.date BETWEEN '%s' AND '%s'";

$conversionGaql = "SELECT segments.date, campaign.id, segments.conversion_action, segments.conversion_action_name, segments.conversion_action_category, $convMetricsFull FROM campaign WHERE segments.date BETWEEN '%s' AND '%s' AND metrics.all_conversions > 0";

$clickGaql = "SELECT click_view.gclid, segments.date, campaign.id, ad_group.id, click_view.ad_group_ad, click_view.keyword, click_view.keyword_info.text, click_view.keyword_info.match_type, segments.click_type, segments.ad_network_type, segments.device, metrics.clicks FROM click_view WHERE segments.date = '%s'";

$campaignGaqlFull = 'SELECT campaign.id, campaign.name, campaign.status, campaign.serving_status, campaign.primary_status, campaign.primary_status_reasons, campaign.advertising_channel_type, campaign.advertising_channel_sub_type, campaign.bidding_strategy_type, campaign.bidding_strategy, campaign.maximize_conversion_value.target_roas, campaign.target_roas.target_roas, campaign.target_cpa.target_cpa_micros, campaign.maximize_conversions.target_cpa_micros, campaign.campaign_budget, campaign_budget.amount_micros, campaign_budget.delivery_method, campaign_budget.period, campaign_budget.explicitly_shared, accessible_bidding_strategy.id, accessible_bidding_strategy.name, accessible_bidding_strategy.type, accessible_bidding_strategy.target_roas.target_roas, accessible_bidding_strategy.maximize_conversion_value.target_roas, accessible_bidding_strategy.target_cpa.target_cpa_micros, accessible_bidding_strategy.maximize_conversions.target_cpa_micros FROM campaign';
$campaignGaqlBudget = 'SELECT campaign.id, campaign.name, campaign.status, campaign.serving_status, campaign.primary_status, campaign.primary_status_reasons, campaign.advertising_channel_type, campaign.advertising_channel_sub_type, campaign.bidding_strategy_type, campaign.bidding_strategy, campaign.maximize_conversion_value.target_roas, campaign.target_roas.target_roas, campaign.target_cpa.target_cpa_micros, campaign.maximize_conversions.target_cpa_micros, campaign.campaign_budget, campaign_budget.amount_micros, campaign_budget.delivery_method, campaign_budget.period, campaign_budget.explicitly_shared FROM campaign';
$campaignGaqlBasic = 'SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type, campaign.bidding_strategy_type, campaign.maximize_conversion_value.target_roas, campaign.target_roas.target_roas FROM campaign';

$conversionActionGaql = 'SELECT conversion_action.id, conversion_action.name, conversion_action.category, conversion_action.type, conversion_action.origin, conversion_action.status, conversion_action.primary_for_goal, conversion_action.include_in_conversions_metric, conversion_action.click_through_lookback_window_days, conversion_action.view_through_lookback_window_days, conversion_action.attribution_model_settings.attribution_model, conversion_action.value_settings.default_value, conversion_action.counting_type FROM conversion_action';

/** Every GAQL field used above, for --fields-check. */
function warehouse_gaql_field_names() {
	return array(
		'segments.date', 'segments.conversion_action', 'segments.conversion_action_name', 'segments.conversion_action_category',
		'segments.click_type', 'segments.ad_network_type', 'segments.device',
		'metrics.cost_micros', 'metrics.clicks', 'metrics.impressions',
		'metrics.conversions', 'metrics.conversions_value', 'metrics.all_conversions', 'metrics.all_conversions_value',
		'metrics.conversions_by_conversion_date', 'metrics.conversions_value_by_conversion_date',
		'metrics.search_impression_share', 'metrics.search_budget_lost_impression_share', 'metrics.search_rank_lost_impression_share',
		'campaign.id', 'campaign.name', 'campaign.status', 'campaign.serving_status', 'campaign.primary_status', 'campaign.primary_status_reasons',
		'campaign.advertising_channel_type', 'campaign.advertising_channel_sub_type', 'campaign.bidding_strategy_type', 'campaign.bidding_strategy',
		'campaign.maximize_conversion_value.target_roas', 'campaign.target_roas.target_roas',
		'campaign.target_cpa.target_cpa_micros', 'campaign.maximize_conversions.target_cpa_micros', 'campaign.campaign_budget',
		'campaign_budget.amount_micros', 'campaign_budget.delivery_method', 'campaign_budget.period', 'campaign_budget.explicitly_shared',
		'accessible_bidding_strategy.id', 'accessible_bidding_strategy.name', 'accessible_bidding_strategy.type',
		'accessible_bidding_strategy.target_roas.target_roas', 'accessible_bidding_strategy.maximize_conversion_value.target_roas',
		'accessible_bidding_strategy.target_cpa.target_cpa_micros', 'accessible_bidding_strategy.maximize_conversions.target_cpa_micros',
		'conversion_action.id', 'conversion_action.name', 'conversion_action.category', 'conversion_action.type', 'conversion_action.origin',
		'conversion_action.status', 'conversion_action.primary_for_goal', 'conversion_action.include_in_conversions_metric',
		'conversion_action.click_through_lookback_window_days', 'conversion_action.view_through_lookback_window_days',
		'conversion_action.attribution_model_settings.attribution_model', 'conversion_action.value_settings.default_value', 'conversion_action.counting_type',
		'click_view.gclid', 'click_view.ad_group_ad', 'click_view.keyword', 'click_view.keyword_info.text', 'click_view.keyword_info.match_type',
		'ad_group.id', 'ad_group_criterion.criterion_id',
	);
}

// ------------------------------------------------------------- helpers

/** Last path segment of a resource name, split on '~' (parent~child ids). */
function warehouse_resource_tail( $pResource ) {
	if( !is_string( $pResource ) || $pResource === '' ) {
		return array();
	}
	$seg = substr( strrchr( '/'.$pResource, '/' ), 1 );
	return explode( '~', $seg );
}

/** PHP list to a PostgreSQL text[] literal, or null. */
function warehouse_pg_text_array( $pList ) {
	if( !is_array( $pList ) || !$pList ) {
		return null;
	}
	$items = array();
	foreach( $pList as $v ) {
		$items[] = '"'.str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), (string)$v ).'"';
	}
	return '{'.implode( ',', $items ).'}';
}

function warehouse_micros( $pValue ) {
	if( $pValue === null || $pValue === '' || !is_numeric( $pValue ) ) {
		return null;
	}
	$v = round( ((float)$pValue) / 1000000.0, 4 );
	return $v > 0 ? $v : null;
}

/** Target ROAS from a campaign or bidding-strategy object; 0/absent = none. */
function warehouse_target_roas( $pObj ) {
	if( !is_array( $pObj ) ) {
		return null;
	}
	$mcv = ads_field( $pObj, 'maximizeConversionValue', 'maximize_conversion_value' );
	if( is_array( $mcv ) ) {
		$v = ads_field( $mcv, 'targetRoas', 'target_roas' );
		if( $v !== null && is_numeric( $v ) && (float)$v > 0 ) {
			return (float)$v;
		}
	}
	$tr = ads_field( $pObj, 'targetRoas', 'target_roas' );
	if( is_array( $tr ) ) {
		$v = ads_field( $tr, 'targetRoas', 'target_roas' );
		if( $v !== null && is_numeric( $v ) && (float)$v > 0 ) {
			return (float)$v;
		}
	} elseif( $tr !== null && is_numeric( $tr ) && (float)$tr > 0 ) {
		return (float)$tr;
	}
	return null;
}

/** Target CPA (account currency) from a campaign or bidding-strategy object. */
function warehouse_target_cpa( $pObj ) {
	if( !is_array( $pObj ) ) {
		return null;
	}
	$tc = ads_field( $pObj, 'targetCpa', 'target_cpa' );
	if( is_array( $tc ) ) {
		$v = warehouse_micros( ads_field( $tc, 'targetCpaMicros', 'target_cpa_micros' ) );
		if( $v !== null ) {
			return $v;
		}
	}
	$mc = ads_field( $pObj, 'maximizeConversions', 'maximize_conversions' );
	if( is_array( $mc ) ) {
		return warehouse_micros( ads_field( $mc, 'targetCpaMicros', 'target_cpa_micros' ) );
	}
	return null;
}

/** Run the first query the API accepts. Returns array( rows, index ). */
function warehouse_search_first_ok( $token, $cid, $pQueries, $pLabel ) {
	$last = null;
	foreach( $pQueries as $i => $q ) {
		try {
			return array( ads_search_stream( $token, $cid, $q ), $i );
		} catch( Exception $e ) {
			$last = $e;
			fwrite( STDERR, "  $pLabel query #$i rejected: ".substr( $e->getMessage(), 0, 300 )."\n" );
		}
	}
	throw $last;
}

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
			'network_code'        => $network,
			'account_id'          => (string)$cid,
			'campaign_id'         => (string)$camp['id'],
			'campaign_name'       => isset( $camp['name'] ) ? $camp['name'] : null,
			'channel'             => isset( $camp['channel'] ) ? $camp['channel'] : null,
			'status'              => isset( $camp['status'] ) ? $camp['status'] : null,
			'primary_status'      => isset( $camp['primary_status'] ) ? $camp['primary_status'] : null,
			'bidding_strategy'    => isset( $camp['bidding'] ) ? $camp['bidding'] : null,
			'bidding_strategy_id' => isset( $camp['bidding_strategy_id'] ) ? $camp['bidding_strategy_id'] : null,
			'target_roas'         => isset( $camp['target_roas'] ) ? $camp['target_roas'] : null,
			'target_cpa'          => isset( $camp['target_cpa'] ) ? $camp['target_cpa'] : null,
			'budget_amount'       => isset( $camp['budget_amount'] ) ? $camp['budget_amount'] : null,
			'extra'               => isset( $camp['extra'] ) ? json_encode( $camp['extra'] ) : '{}',
			'first_seen_at'       => date( 'c' ),
			'last_seen_at'        => date( 'c' ),
		),
		array(
			'campaign_name', 'channel', 'status', 'primary_status', 'bidding_strategy', 'bidding_strategy_id',
			'target_roas', 'target_cpa', 'budget_amount', 'extra', 'last_seen_at',
		)
	);
}

/** One settings row per campaign per account-local day. Same-day reruns overwrite. */
function warehouse_upsert_settings_snapshot( $network, $cid, $snapshotDate, $camp ) {
	ads_upsert_touch(
		'stats_ad_campaign_settings_daily',
		array( 'snapshot_date', 'network_code', 'account_id', 'campaign_id' ),
		array(
			'snapshot_date'          => $snapshotDate,
			'network_code'           => $network,
			'account_id'             => (string)$cid,
			'campaign_id'            => (string)$camp['id'],
			'campaign_name'          => isset( $camp['name'] ) ? $camp['name'] : null,
			'status'                 => isset( $camp['status'] ) ? $camp['status'] : null,
			'serving_status'         => isset( $camp['serving_status'] ) ? $camp['serving_status'] : null,
			'primary_status'         => isset( $camp['primary_status'] ) ? $camp['primary_status'] : null,
			'primary_status_reasons' => warehouse_pg_text_array( isset( $camp['primary_status_reasons'] ) ? $camp['primary_status_reasons'] : null ),
			'channel'                => isset( $camp['channel'] ) ? $camp['channel'] : null,
			'bidding_strategy_type'  => isset( $camp['bidding'] ) ? $camp['bidding'] : null,
			'bidding_scope'          => isset( $camp['bidding_scope'] ) ? $camp['bidding_scope'] : null,
			'bidding_strategy_id'    => isset( $camp['bidding_strategy_id'] ) ? $camp['bidding_strategy_id'] : null,
			'bidding_strategy_name'  => isset( $camp['bidding_strategy_name'] ) ? $camp['bidding_strategy_name'] : null,
			'target_roas'            => isset( $camp['target_roas'] ) ? $camp['target_roas'] : null,
			'target_cpa'             => isset( $camp['target_cpa'] ) ? $camp['target_cpa'] : null,
			'budget_amount'          => isset( $camp['budget_amount'] ) ? $camp['budget_amount'] : null,
			'budget_delivery'        => isset( $camp['budget_delivery'] ) ? $camp['budget_delivery'] : null,
			'budget_period'          => isset( $camp['budget_period'] ) ? $camp['budget_period'] : null,
			'budget_shared'          => isset( $camp['budget_shared'] ) ? (bool)$camp['budget_shared'] : null,
			'extra'                  => isset( $camp['extra'] ) ? json_encode( $camp['extra'] ) : '{}',
			'pulled_at'              => date( 'c' ),
		),
		array(
			'campaign_name', 'status', 'serving_status', 'primary_status', 'primary_status_reasons', 'channel',
			'bidding_strategy_type', 'bidding_scope', 'bidding_strategy_id', 'bidding_strategy_name',
			'target_roas', 'target_cpa', 'budget_amount', 'budget_delivery', 'budget_period', 'budget_shared',
			'extra', 'pulled_at',
		)
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
		'network_conversions', 'network_value',
		'all_conversions', 'all_conversions_value', 'conversions_by_conv_date', 'value_by_conv_date',
		'currency', 'extra', 'pulled_at',
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
		$binds[] = $row['all_conversions'];
		$binds[] = $row['all_conversions_value'];
		$binds[] = $row['conversions_by_conv_date'];
		$binds[] = $row['value_by_conv_date'];
		$binds[] = isset( $row['currency'] ) ? $row['currency'] : 'USD';
		$binds[] = isset( $row['extra'] ) ? json_encode( $row['extra'] ) : '{}';
		$binds[] = $now;
	}
	// A basic (fallback) pull leaves the extended columns null; keep what an
	// earlier extended pull stored for that day.
	$sql = 'INSERT INTO stats_ad_metrics_daily ('.implode( ',', $cols ).') VALUES '.implode( ',', $values ).'
		ON CONFLICT (metric_date, network_code, account_id, grain, campaign_id, adgroup_id, ad_id, keyword_id) DO UPDATE SET
		  spend = EXCLUDED.spend,
		  clicks = EXCLUDED.clicks,
		  impressions = EXCLUDED.impressions,
		  network_conversions = EXCLUDED.network_conversions,
		  network_value = EXCLUDED.network_value,
		  all_conversions = COALESCE(EXCLUDED.all_conversions, stats_ad_metrics_daily.all_conversions),
		  all_conversions_value = COALESCE(EXCLUDED.all_conversions_value, stats_ad_metrics_daily.all_conversions_value),
		  conversions_by_conv_date = COALESCE(EXCLUDED.conversions_by_conv_date, stats_ad_metrics_daily.conversions_by_conv_date),
		  value_by_conv_date = COALESCE(EXCLUDED.value_by_conv_date, stats_ad_metrics_daily.value_by_conv_date),
		  currency = EXCLUDED.currency,
		  extra = EXCLUDED.extra,
		  pulled_at = EXCLUDED.pulled_at';
	$gBitSystem->mDb->query( $sql, $binds );
	$batch = array();
}

/** Impression-share columns only (campaign grain). Other columns untouched. */
function warehouse_flush_is( &$batch ) {
	global $gBitSystem;
	if( empty( $batch ) ) {
		return;
	}
	$cols = array(
		'metric_date', 'network_code', 'account_id', 'grain', 'campaign_id', 'adgroup_id', 'ad_id', 'keyword_id',
		'search_is', 'search_budget_lost_is', 'search_rank_lost_is', 'currency',
	);
	$values = array();
	$binds = array();
	foreach( $batch as $row ) {
		$values[] = '('.implode( ',', array_fill( 0, count( $cols ), '?' ) ).')';
		$binds[] = $row['metric_date'];
		$binds[] = $row['network_code'];
		$binds[] = $row['account_id'];
		$binds[] = 'campaign';
		$binds[] = (string)$row['campaign_id'];
		$binds[] = '';
		$binds[] = '';
		$binds[] = '';
		$binds[] = $row['search_is'];
		$binds[] = $row['search_budget_lost_is'];
		$binds[] = $row['search_rank_lost_is'];
		$binds[] = $row['currency'];
	}
	$sql = 'INSERT INTO stats_ad_metrics_daily ('.implode( ',', $cols ).') VALUES '.implode( ',', $values ).'
		ON CONFLICT (metric_date, network_code, account_id, grain, campaign_id, adgroup_id, ad_id, keyword_id) DO UPDATE SET
		  search_is = EXCLUDED.search_is,
		  search_budget_lost_is = EXCLUDED.search_budget_lost_is,
		  search_rank_lost_is = EXCLUDED.search_rank_lost_is';
	$gBitSystem->mDb->query( $sql, $binds );
	$batch = array();
}

function warehouse_metric_num( $m, $camel, $snake ) {
	$v = ads_field( $m, $camel, $snake );
	return ( $v === null || !is_numeric( $v ) ) ? null : (float)$v;
}

function warehouse_metrics_from_api_row( $r, $network, $cid, $grain, $currency ) {
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
		'metric_date'              => ads_field( $seg, 'date' ),
		'network_code'             => $network,
		'account_id'               => (string)$cid,
		'grain'                    => $grain,
		'campaign_id'              => (string)ads_field( $camp, 'id' ),
		'adgroup_id'               => $grain === 'campaign' ? '' : (string)ads_field( $ag, 'id' ),
		'ad_id'                    => $grain === 'ad' ? (string)ads_field( $ad, 'id' ) : '',
		'keyword_id'               => $grain === 'keyword' ? (string)ads_field( $crit, 'criterionId', 'criterion_id' ) : '',
		'spend'                    => round( ((float)$cost) / 1000000.0, 4 ),
		'clicks'                   => (int)( ads_field( $m, 'clicks' ) ?: 0 ),
		'impressions'              => (int)( ads_field( $m, 'impressions' ) ?: 0 ),
		'network_conversions'      => warehouse_metric_num( $m, 'conversions', 'conversions' ),
		'network_value'            => warehouse_metric_num( $m, 'conversionsValue', 'conversions_value' ),
		'all_conversions'          => warehouse_metric_num( $m, 'allConversions', 'all_conversions' ),
		'all_conversions_value'    => warehouse_metric_num( $m, 'allConversionsValue', 'all_conversions_value' ),
		'conversions_by_conv_date' => warehouse_metric_num( $m, 'conversionsByConversionDate', 'conversions_by_conversion_date' ),
		'value_by_conv_date'       => warehouse_metric_num( $m, 'conversionsValueByConversionDate', 'conversions_value_by_conversion_date' ),
		'currency'                 => $currency,
		'extra'                    => array( 'cost_micros' => (int)$cost, 'source' => 'google_ads_api' ),
	);
}

function warehouse_is_from_api_row( $r, $network, $cid, $currency ) {
	$camp = isset( $r['campaign'] ) ? $r['campaign'] : array();
	$m = isset( $r['metrics'] ) ? $r['metrics'] : array();
	$seg = isset( $r['segments'] ) ? $r['segments'] : array();
	return array(
		'metric_date'           => ads_field( $seg, 'date' ),
		'network_code'          => $network,
		'account_id'            => (string)$cid,
		'campaign_id'           => (string)ads_field( $camp, 'id' ),
		'search_is'             => warehouse_metric_num( $m, 'searchImpressionShare', 'search_impression_share' ),
		'search_budget_lost_is' => warehouse_metric_num( $m, 'searchBudgetLostImpressionShare', 'search_budget_lost_impression_share' ),
		'search_rank_lost_is'   => warehouse_metric_num( $m, 'searchRankLostImpressionShare', 'search_rank_lost_impression_share' ),
		'currency'              => $currency,
	);
}

function warehouse_flush_conversions( &$batch ) {
	global $gBitSystem;
	if( empty( $batch ) ) {
		return;
	}
	$cols = array(
		'metric_date', 'network_code', 'account_id', 'campaign_id', 'conversion_action_id',
		'conversion_action_name', 'conversion_action_category',
		'conversions', 'conversions_value', 'all_conversions', 'all_conversions_value',
		'conversions_by_conv_date', 'value_by_conv_date', 'currency', 'extra', 'pulled_at',
	);
	$now = date( 'c' );
	$values = array();
	$binds = array();
	foreach( $batch as $row ) {
		$values[] = '('.implode( ',', array_fill( 0, count( $cols ), '?' ) ).')';
		foreach( $cols as $c ) {
			if( $c === 'pulled_at' ) {
				$binds[] = $now;
			} elseif( $c === 'extra' ) {
				$binds[] = json_encode( $row['extra'] );
			} else {
				$binds[] = $row[$c];
			}
		}
	}
	$sql = 'INSERT INTO stats_ad_conversion_daily ('.implode( ',', $cols ).') VALUES '.implode( ',', $values ).'
		ON CONFLICT (metric_date, network_code, account_id, campaign_id, conversion_action_id) DO UPDATE SET
		  conversion_action_name = EXCLUDED.conversion_action_name,
		  conversion_action_category = EXCLUDED.conversion_action_category,
		  conversions = EXCLUDED.conversions,
		  conversions_value = EXCLUDED.conversions_value,
		  all_conversions = EXCLUDED.all_conversions,
		  all_conversions_value = EXCLUDED.all_conversions_value,
		  conversions_by_conv_date = EXCLUDED.conversions_by_conv_date,
		  value_by_conv_date = EXCLUDED.value_by_conv_date,
		  currency = EXCLUDED.currency,
		  extra = EXCLUDED.extra,
		  pulled_at = EXCLUDED.pulled_at';
	$gBitSystem->mDb->query( $sql, $binds );
	$batch = array();
}

function warehouse_conversion_from_api_row( $r, $network, $cid, $currency ) {
	$camp = isset( $r['campaign'] ) ? $r['campaign'] : array();
	$m = isset( $r['metrics'] ) ? $r['metrics'] : array();
	$seg = isset( $r['segments'] ) ? $r['segments'] : array();
	$res = ads_field( $seg, 'conversionAction', 'conversion_action' );
	$tail = warehouse_resource_tail( $res );
	return array(
		'metric_date'                => ads_field( $seg, 'date' ),
		'network_code'               => $network,
		'account_id'                 => (string)$cid,
		'campaign_id'                => (string)ads_field( $camp, 'id' ),
		'conversion_action_id'       => isset( $tail[0] ) ? (string)$tail[0] : '',
		'conversion_action_name'     => ads_field( $seg, 'conversionActionName', 'conversion_action_name' ),
		'conversion_action_category' => ads_field( $seg, 'conversionActionCategory', 'conversion_action_category' ),
		'conversions'                => warehouse_metric_num( $m, 'conversions', 'conversions' ),
		'conversions_value'          => warehouse_metric_num( $m, 'conversionsValue', 'conversions_value' ),
		'all_conversions'            => warehouse_metric_num( $m, 'allConversions', 'all_conversions' ),
		'all_conversions_value'      => warehouse_metric_num( $m, 'allConversionsValue', 'all_conversions_value' ),
		'conversions_by_conv_date'   => warehouse_metric_num( $m, 'conversionsByConversionDate', 'conversions_by_conversion_date' ),
		'value_by_conv_date'         => warehouse_metric_num( $m, 'conversionsValueByConversionDate', 'conversions_value_by_conversion_date' ),
		'currency'                   => $currency,
		'extra'                      => array( 'source' => 'google_ads_api' ),
	);
}

function warehouse_flush_clicks( &$batch ) {
	global $gBitSystem;
	if( empty( $batch ) ) {
		return;
	}
	$cols = array(
		'network_code', 'account_id', 'click_id', 'click_date', 'campaign_id', 'adgroup_id', 'ad_id',
		'keyword_id', 'keyword_text', 'match_type', 'click_type', 'network_type', 'device', 'extra', 'pulled_at',
	);
	$now = date( 'c' );
	$values = array();
	$binds = array();
	$seen = array();
	foreach( $batch as $row ) {
		if( isset( $seen[$row['click_id']] ) ) {
			continue; // one click id can appear with several segments; keep the first
		}
		$seen[$row['click_id']] = true;
		$values[] = '('.implode( ',', array_fill( 0, count( $cols ), '?' ) ).')';
		foreach( $cols as $c ) {
			if( $c === 'pulled_at' ) {
				$binds[] = $now;
			} elseif( $c === 'extra' ) {
				$binds[] = json_encode( $row['extra'] );
			} else {
				$binds[] = $row[$c];
			}
		}
	}
	$batch = array();
	if( !$values ) {
		return;
	}
	$sql = 'INSERT INTO stats_ad_click ('.implode( ',', $cols ).') VALUES '.implode( ',', $values ).'
		ON CONFLICT (network_code, account_id, click_id) DO UPDATE SET
		  click_date = EXCLUDED.click_date,
		  campaign_id = EXCLUDED.campaign_id,
		  adgroup_id = COALESCE(EXCLUDED.adgroup_id, stats_ad_click.adgroup_id),
		  ad_id = COALESCE(EXCLUDED.ad_id, stats_ad_click.ad_id),
		  keyword_id = COALESCE(EXCLUDED.keyword_id, stats_ad_click.keyword_id),
		  keyword_text = COALESCE(EXCLUDED.keyword_text, stats_ad_click.keyword_text),
		  match_type = COALESCE(EXCLUDED.match_type, stats_ad_click.match_type),
		  click_type = EXCLUDED.click_type,
		  network_type = EXCLUDED.network_type,
		  device = EXCLUDED.device,
		  extra = EXCLUDED.extra,
		  pulled_at = EXCLUDED.pulled_at';
	$gBitSystem->mDb->query( $sql, $binds );
}

function warehouse_click_from_api_row( $r, $network, $cid ) {
	$camp = isset( $r['campaign'] ) ? $r['campaign'] : array();
	$seg = isset( $r['segments'] ) ? $r['segments'] : array();
	$cv = isset( $r['clickView'] ) ? $r['clickView'] : ( isset( $r['click_view'] ) ? $r['click_view'] : array() );
	$ag = isset( $r['adGroup'] ) ? $r['adGroup'] : ( isset( $r['ad_group'] ) ? $r['ad_group'] : array() );
	$m = isset( $r['metrics'] ) ? $r['metrics'] : array();
	$adTail = warehouse_resource_tail( ads_field( $cv, 'adGroupAd', 'ad_group_ad' ) );
	$kwTail = warehouse_resource_tail( ads_field( $cv, 'keyword' ) );
	$kwInfo = ads_field( $cv, 'keywordInfo', 'keyword_info' );
	if( !is_array( $kwInfo ) ) {
		$kwInfo = array();
	}
	$agId = ads_field( $ag, 'id' );
	if( $agId === null && isset( $adTail[0] ) ) {
		$agId = $adTail[0];
	}
	return array(
		'network_code' => $network,
		'account_id'   => (string)$cid,
		'click_id'     => (string)ads_field( $cv, 'gclid' ),
		'click_date'   => ads_field( $seg, 'date' ),
		'campaign_id'  => (string)ads_field( $camp, 'id' ),
		'adgroup_id'   => $agId === null ? null : (string)$agId,
		'ad_id'        => isset( $adTail[1] ) ? (string)$adTail[1] : null,
		'keyword_id'   => isset( $kwTail[1] ) ? (string)$kwTail[1] : null,
		'keyword_text' => ads_field( $kwInfo, 'text' ),
		'match_type'   => ads_field( $kwInfo, 'matchType', 'match_type' ),
		'click_type'   => ads_field( $seg, 'clickType', 'click_type' ),
		'network_type' => ads_field( $seg, 'adNetworkType', 'ad_network_type' ),
		'device'       => ads_field( $seg, 'device' ),
		'extra'        => array( 'clicks' => (int)( ads_field( $m, 'clicks' ) ?: 0 ), 'source' => 'google_ads_api' ),
	);
}

// --------------------------------------------------------- fields check

if( $doFieldsCheck ) {
	$token = ads_access_token();
	$names = warehouse_gaql_field_names();
	$info = ads_fields_check( $token, $names );
	$with = array( 'segments.date', 'segments.conversion_action_name', 'metrics.cost_micros' );
	echo "API version ".ads_api_version()."\n";
	echo str_pad( 'status', 8 ).str_pad( 'field', 68 )."selectable with\n";
	foreach( $names as $f ) {
		if( empty( $info[$f] ) ) {
			echo str_pad( 'MISSING', 8 ).$f."\n";
			continue;
		}
		$i = $info[$f];
		$flags = array();
		if( strpos( $f, 'metrics.' ) === 0 || strpos( $f, 'segments.' ) === 0 ) {
			foreach( $with as $w ) {
				if( $w === $f ) {
					continue;
				}
				$flags[] = ( in_array( $w, $i['selectable_with'], true ) ? '+' : '-' ).$w;
			}
		}
		echo str_pad( $i['selectable'] ? 'ok' : 'NOSEL', 8 ).str_pad( $f, 68 ).implode( ' ', $flags )."\n";
	}
	exit( 0 );
}

// -------------------------------------------------------------- migrate

if( $doMigrate && !ads_warehouse_table_exists( 'ads_spend_daily' ) ) {
	fwrite( STDERR, "skip migrate: ads_spend_daily not on this database\n" );
	$doMigrate = false;
}

if( $doMigrate ) {
	fwrite( STDERR, "migrate ads_spend_daily -> stats_ad_metrics_daily grain=campaign\n" );
	$db->query(
		"INSERT INTO stats_ad_account (network_code, account_id, account_name, is_manager, currency, first_seen_at, last_seen_at)
		 SELECT 'google', customer_id::text, NULL, false, 'USD', now(), now()
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
	$db->query(
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
if( $doEntities || $metrics || $doConversions || $doClicks ) {
	$token = ads_access_token();
	fwrite( STDERR, "got Ads token, customer $cid\n" );
}

// ------------------------------------------------------------- entities

$accountTz = null;
if( $doEntities ) {
	fwrite( STDERR, "pull customer\n" );
	$custRows = ads_search_stream( $token, $cid, 'SELECT customer.id, customer.descriptive_name, customer.currency_code, customer.time_zone, customer.status FROM customer LIMIT 1' );
	$c0 = isset( $custRows[0]['customer'] ) ? $custRows[0]['customer'] : array();
	$accountTz = ads_field( $c0, 'timeZone', 'time_zone' );
	warehouse_upsert_account( $network, $cid, array(
		'name'     => ads_field( $c0, 'descriptiveName', 'descriptive_name' ),
		'currency' => ads_field( $c0, 'currencyCode', 'currency_code' ),
		'timezone' => $accountTz,
		'status'   => ads_field( $c0, 'status' ),
	) );

	// Settings history is stamped with the account-local day so the nightly
	// run after midnight records the new day.
	$tz = $accountTz ? $accountTz : ads_warehouse_account_timezone( $network, $cid );
	try {
		$snapshotDate = $tz ? ( new DateTime( 'now', new DateTimeZone( $tz ) ) )->format( 'Y-m-d' ) : date( 'Y-m-d' );
	} catch( Exception $e ) {
		$snapshotDate = date( 'Y-m-d' );
	}

	fwrite( STDERR, "pull campaigns\n" );
	list( $campRows, $campQueryIdx ) = warehouse_search_first_ok(
		$token, $cid, array( $campaignGaqlFull, $campaignGaqlBudget, $campaignGaqlBasic ), 'campaign'
	);
	$n = 0;
	foreach( $campRows as $r ) {
		$c = isset( $r['campaign'] ) ? $r['campaign'] : array();
		$bud = isset( $r['campaignBudget'] ) ? $r['campaignBudget'] : ( isset( $r['campaign_budget'] ) ? $r['campaign_budget'] : array() );
		$abs = isset( $r['accessibleBiddingStrategy'] ) ? $r['accessibleBiddingStrategy'] : ( isset( $r['accessible_bidding_strategy'] ) ? $r['accessible_bidding_strategy'] : array() );
		$portfolio = ads_field( $c, 'biddingStrategy', 'bidding_strategy' );
		$troas = warehouse_target_roas( $c );
		$tcpa = warehouse_target_cpa( $c );
		if( $troas === null && $abs ) {
			$troas = warehouse_target_roas( $abs );
		}
		if( $tcpa === null && $abs ) {
			$tcpa = warehouse_target_cpa( $abs );
		}
		$bsId = ads_field( $abs, 'id' );
		if( $bsId === null && $portfolio ) {
			$tail = warehouse_resource_tail( $portfolio );
			$bsId = isset( $tail[0] ) ? $tail[0] : null;
		}
		$reasons = ads_field( $c, 'primaryStatusReasons', 'primary_status_reasons' );
		$camp = array(
			'id'                     => ads_field( $c, 'id' ),
			'name'                   => ads_field( $c, 'name' ),
			'channel'                => ads_field( $c, 'advertisingChannelType', 'advertising_channel_type' ),
			'status'                 => ads_field( $c, 'status' ),
			'serving_status'         => ads_field( $c, 'servingStatus', 'serving_status' ),
			'primary_status'         => ads_field( $c, 'primaryStatus', 'primary_status' ),
			'primary_status_reasons' => is_array( $reasons ) ? $reasons : null,
			'bidding'                => ads_field( $c, 'biddingStrategyType', 'bidding_strategy_type' ),
			'bidding_scope'          => $portfolio ? 'portfolio' : 'campaign',
			'bidding_strategy_id'    => $bsId === null ? null : (string)$bsId,
			'bidding_strategy_name'  => ads_field( $abs, 'name' ),
			'target_roas'            => $troas,
			'target_cpa'             => $tcpa,
			'budget_amount'          => warehouse_micros( ads_field( $bud, 'amountMicros', 'amount_micros' ) ),
			'budget_delivery'        => ads_field( $bud, 'deliveryMethod', 'delivery_method' ),
			'budget_period'          => ads_field( $bud, 'period' ),
			'budget_shared'          => isset( $bud['explicitlyShared'] ) ? (bool)$bud['explicitlyShared'] : ( isset( $bud['explicitly_shared'] ) ? (bool)$bud['explicitly_shared'] : null ),
			'extra'                  => array_filter( array(
				'channel_sub_type'      => ads_field( $c, 'advertisingChannelSubType', 'advertising_channel_sub_type' ),
				'portfolio_strategy'    => $portfolio,
				'portfolio_type'        => ads_field( $abs, 'type' ),
				'campaign_query'        => $campQueryIdx,
			) ),
		);
		if( $camp['id'] === null ) {
			continue;
		}
		warehouse_upsert_campaign( $network, $cid, $camp );
		warehouse_upsert_settings_snapshot( $network, $cid, $snapshotDate, $camp );
		$n++;
	}
	fwrite( STDERR, "upserted $n campaigns (query #$campQueryIdx) and settings for $snapshotDate\n" );

	fwrite( STDERR, "pull conversion actions\n" );
	$n = 0;
	try {
		foreach( ads_search_stream( $token, $cid, $conversionActionGaql ) as $r ) {
			$ca = isset( $r['conversionAction'] ) ? $r['conversionAction'] : ( isset( $r['conversion_action'] ) ? $r['conversion_action'] : array() );
			$caId = ads_field( $ca, 'id' );
			if( $caId === null ) {
				continue;
			}
			$ams = ads_field( $ca, 'attributionModelSettings', 'attribution_model_settings' );
			$vs = ads_field( $ca, 'valueSettings', 'value_settings' );
			$cw = ads_field( $ca, 'clickThroughLookbackWindowDays', 'click_through_lookback_window_days' );
			$vw = ads_field( $ca, 'viewThroughLookbackWindowDays', 'view_through_lookback_window_days' );
			$dv = is_array( $vs ) ? ads_field( $vs, 'defaultValue', 'default_value' ) : null;
			ads_upsert_touch(
				'stats_ad_conversion_action',
				array( 'network_code', 'account_id', 'conversion_action_id' ),
				array(
					'network_code'           => $network,
					'account_id'             => (string)$cid,
					'conversion_action_id'   => (string)$caId,
					'action_name'            => ads_field( $ca, 'name' ),
					'category'               => ads_field( $ca, 'category' ),
					'action_type'            => ads_field( $ca, 'type' ),
					'origin'                 => ads_field( $ca, 'origin' ),
					'status'                 => ads_field( $ca, 'status' ),
					'primary_for_goal'       => !empty( $ca['primaryForGoal'] ) || !empty( $ca['primary_for_goal'] ),
					'include_in_conversions' => !empty( $ca['includeInConversionsMetric'] ) || !empty( $ca['include_in_conversions_metric'] ),
					'click_window_days'      => is_numeric( $cw ) ? (int)$cw : null,
					'view_window_days'       => is_numeric( $vw ) ? (int)$vw : null,
					'attribution_model'      => is_array( $ams ) ? ads_field( $ams, 'attributionModel', 'attribution_model' ) : null,
					'default_value'          => is_numeric( $dv ) ? (float)$dv : null,
					'counting_type'          => ads_field( $ca, 'countingType', 'counting_type' ),
					'extra'                  => '{}',
					'first_seen_at'          => date( 'c' ),
					'last_seen_at'           => date( 'c' ),
				),
				array(
					'action_name', 'category', 'action_type', 'origin', 'status', 'primary_for_goal', 'include_in_conversions',
					'click_window_days', 'view_window_days', 'attribution_model', 'default_value', 'counting_type', 'last_seen_at',
				)
			);
			$n++;
		}
		fwrite( STDERR, "upserted $n conversion actions\n" );
		// The purchase actions' click-through lookback is the network's own
		// click window. A value an operator typed on the ROAS page wins.
		$db->query(
			"UPDATE stats_ad_network n
			    SET click_window_days = s.w, window_source = 'network'
			   FROM (SELECT MAX(click_window_days) AS w
			           FROM stats_ad_conversion_action
			          WHERE network_code = ? AND account_id = ?
			            AND include_in_conversions AND category = 'PURCHASE' AND status = 'ENABLED') s
			  WHERE n.network_code = ? AND s.w IS NOT NULL
			    AND (n.window_source IS NULL OR n.window_source <> 'user')",
			array( $network, (string)$cid, $network )
		);
	} catch( Exception $e ) {
		fwrite( STDERR, "conversion_action pull skipped: ".substr( $e->getMessage(), 0, 300 )."\n" );
	}

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

$currency = ads_warehouse_account_currency( $network, $cid );

// -------------------------------------------------------------- metrics

foreach( $metrics as $grain ) {
	if( empty( $metricGaql[$grain] ) ) {
		fwrite( STDERR, "unknown grain $grain\n" );
		continue;
	}
	fwrite( STDERR, "pull metrics grain=$grain $since..$until\n" );
	$n = 0;
	$batch = array();
	$db->StartTrans();
	$collect = function( $r ) use ( &$batch, &$n, $network, $cid, $grain, $currency ) {
		$batch[] = warehouse_metrics_from_api_row( $r, $network, $cid, $grain, $currency );
		$n++;
		if( count( $batch ) >= 400 ) {
			warehouse_flush_metrics( $batch );
		}
		if( $n % 5000 === 0 ) {
			fwrite( STDERR, "  $n rows\n" );
		}
	};
	try {
		ads_search_each( $token, $cid, sprintf( $metricGaql[$grain], $since, $until ), $collect );
	} catch( Exception $e ) {
		if( $n > 0 ) {
			throw $e;
		}
		fwrite( STDERR, "  extended metrics rejected (".substr( $e->getMessage(), 0, 200 )."); retry basic\n" );
		$batch = array();
		ads_search_each( $token, $cid, sprintf( $metricGaqlBasic[$grain], $since, $until ), $collect );
	}
	warehouse_flush_metrics( $batch );
	$db->CompleteTrans();
	fwrite( STDERR, "upserted $n $grain-day rows\n" );

	if( $grain === 'campaign' ) {
		fwrite( STDERR, "pull impression share grain=campaign $since..$until\n" );
		$n = 0;
		$batch = array();
		$db->StartTrans();
		try {
			ads_search_each( $token, $cid, sprintf( $isGaql, $since, $until ), function( $r ) use ( &$batch, &$n, $network, $cid, $currency ) {
				$batch[] = warehouse_is_from_api_row( $r, $network, $cid, $currency );
				$n++;
				if( count( $batch ) >= 400 ) {
					warehouse_flush_is( $batch );
				}
			} );
			warehouse_flush_is( $batch );
			$db->CompleteTrans();
			fwrite( STDERR, "updated impression share on $n campaign-day rows\n" );
		} catch( Exception $e ) {
			$db->RollbackTrans();
			fwrite( STDERR, "impression share pull skipped: ".substr( $e->getMessage(), 0, 300 )."\n" );
		}
	}
}

// ---------------------------------------------------------- conversions

if( $doConversions ) {
	fwrite( STDERR, "pull conversion actions by campaign/day $since..$until\n" );
	$n = 0;
	$batch = array();
	$db->StartTrans();
	try {
		// Window replace: actions that fell to zero would otherwise linger.
		$db->query(
			"DELETE FROM stats_ad_conversion_daily WHERE network_code = ? AND account_id = ? AND metric_date BETWEEN ?::date AND ?::date",
			array( $network, (string)$cid, $since, $until )
		);
		ads_search_each( $token, $cid, sprintf( $conversionGaql, $since, $until ), function( $r ) use ( &$batch, &$n, $network, $cid, $currency ) {
			$batch[] = warehouse_conversion_from_api_row( $r, $network, $cid, $currency );
			$n++;
			if( count( $batch ) >= 400 ) {
				warehouse_flush_conversions( $batch );
			}
			if( $n % 5000 === 0 ) {
				fwrite( STDERR, "  $n rows\n" );
			}
		} );
		warehouse_flush_conversions( $batch );
		$db->CompleteTrans();
		fwrite( STDERR, "stored $n conversion-action rows\n" );
	} catch( Exception $e ) {
		$db->FailTrans();
		$db->CompleteTrans();
		fwrite( STDERR, "conversion pull failed, window left unchanged: ".substr( $e->getMessage(), 0, 300 )."\n" );
	}
}

// --------------------------------------------------------------- clicks

if( $doClicks ) {
	// click_view accepts one day per query and keeps 90 days.
	$oldest = date( 'Y-m-d', strtotime( '-89 days' ) );
	$from = max( $since, $oldest );
	$to = min( $until, date( 'Y-m-d' ) );
	if( $from > $to ) {
		fwrite( STDERR, "clicks: window $since..$until is older than the network keeps ($oldest); skipped\n" );
	} else {
		if( $from !== $since ) {
			fwrite( STDERR, "clicks: network keeps 90 days; pulling $from..$to\n" );
		}
		$total = 0;
		$days = 0;
		for( $d = $from; $d <= $to; $d = date( 'Y-m-d', strtotime( $d.' +1 day' ) ) ) {
			$n = 0;
			$batch = array();
			$db->StartTrans();
			try {
				ads_search_each( $token, $cid, sprintf( $clickGaql, $d ), function( $r ) use ( &$batch, &$n, $network, $cid ) {
					$row = warehouse_click_from_api_row( $r, $network, $cid );
					if( $row['click_id'] === '' || $row['campaign_id'] === '' ) {
						return;
					}
					$batch[] = $row;
					$n++;
					if( count( $batch ) >= 400 ) {
						warehouse_flush_clicks( $batch );
					}
				} );
				warehouse_flush_clicks( $batch );
				$db->CompleteTrans();
				$total += $n;
				$days++;
			} catch( Exception $e ) {
				$db->RollbackTrans();
				fwrite( STDERR, "  clicks $d skipped: ".substr( $e->getMessage(), 0, 200 )."\n" );
			}
		}
		fwrite( STDERR, "stored $total clicks over $days days\n" );
	}
}

// -------------------------------------------------------------- summary

$summary = $db->getAll(
	"SELECT 'network' AS k, COUNT(*)::text AS n FROM stats_ad_network
	 UNION ALL SELECT 'account', COUNT(*)::text FROM stats_ad_account
	 UNION ALL SELECT 'campaign', COUNT(*)::text FROM stats_ad_campaign
	 UNION ALL SELECT 'campaign_settings_days', COUNT(*)::text FROM stats_ad_campaign_settings_daily
	 UNION ALL SELECT 'conversion_action', COUNT(*)::text FROM stats_ad_conversion_action
	 UNION ALL SELECT 'adgroup', COUNT(*)::text FROM stats_ad_adgroup
	 UNION ALL SELECT 'ad', COUNT(*)::text FROM stats_ad_ad
	 UNION ALL SELECT 'keyword', COUNT(*)::text FROM stats_ad_keyword
	 UNION ALL SELECT 'metrics_campaign', COUNT(*)::text FROM stats_ad_metrics_daily WHERE grain='campaign'
	 UNION ALL SELECT 'metrics_adgroup', COUNT(*)::text FROM stats_ad_metrics_daily WHERE grain='adgroup'
	 UNION ALL SELECT 'metrics_keyword', COUNT(*)::text FROM stats_ad_metrics_daily WHERE grain='keyword'
	 UNION ALL SELECT 'conversion_daily', COUNT(*)::text FROM stats_ad_conversion_daily
	 UNION ALL SELECT 'click', COUNT(*)::text FROM stats_ad_click"
);
foreach( $summary as $s ) {
	fwrite( STDERR, $s['k'].'='.$s['n']."\n" );
}
