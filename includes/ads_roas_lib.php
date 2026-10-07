<?php
/**
 * First-party ROAS queries.
 *
 * Cost is warehouse spend (any network): SUM(stats_ad_metrics_daily.spend)
 * for metric dates in the report range (the advertiser's click dates).
 *
 * Revenue comes from the install's revenue source (Bitcommerce paid orders
 * unless another package registers `stats_revenue_source_function`, see
 * ads_roas_revenue_source()) joined to stats_ad_order_attribution. Three
 * measures are kept apart because they pair with different network numbers:
 *
 *   period revenue   orders purchased in the range by the campaign's
 *                    first-touch customers (any registration date).
 *                    Pairs with the network's conversion-date value.
 *   cohort revenue   customers whose first touch is in the range, orders
 *                    within the click window of that first touch (may fall
 *                    after `until`). Pairs with the network's click-dated value.
 *   cohort LTV       the same customers' paid orders to date, no cap.
 *
 * First touch is the stored click date when the click was matched, else the
 * registration time. The network's target is spend-weighted over the daily
 * settings history; days before the first snapshot use the current target.
 * The target is a bid target the network delivers toward, not a floor.
 *
 * Warehouse tables are optional. Without a revenue source, spend still
 * reports and value is zero. No ad-network writes.
 */

require_once( dirname( __FILE__ ).'/ads_warehouse_lib.php' );

// --------------------------------------------------------------- dates

function ads_roas_date_ok( $pDate ) {
	if( !is_string( $pDate ) || !preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $pDate, $m ) ) {
		return false;
	}
	return checkdate( (int)$m[2], (int)$m[3], (int)$m[1] );
}

function ads_roas_periods() {
	return array( 'day', 'week', 'month', 'quarter', 'year' );
}

/**
 * Period label for a date, byte-identical to the SQL TO_CHAR output the
 * registration reports use (BitDb::getPeriodFormat): 'Y-m-d', 'Y Week WW'
 * (WW = (day of year - 1) / 7 + 1, not ISO), 'Y-m', 'Y-Qn', 'Y'.
 */
function ads_roas_timeframe_for( $pPeriod, $pDate ) {
	$ts = strtotime( $pDate.' 12:00:00' );
	if( $ts === false ) {
		return null;
	}
	switch( $pPeriod ) {
		case 'day':
			return date( 'Y-m-d', $ts );
		case 'week':
			$ww = intdiv( (int)date( 'z', $ts ), 7 ) + 1;
			return date( 'Y', $ts ).' Week '.str_pad( $ww, 2, '0', STR_PAD_LEFT );
		case 'quarter':
			return date( 'Y', $ts ).'-Q'.( intdiv( (int)date( 'n', $ts ) - 1, 3 ) + 1 );
		case 'year':
			return date( 'Y', $ts );
		case 'month':
		default:
			return date( 'Y-m', $ts );
	}
}

/**
 * Inverse of ads_roas_timeframe_for(): since/until for a period label.
 * @return array{since,until,period,timeframe,label}|false
 */
function ads_roas_period_range( $pPeriod, $pTimeframe ) {
	$tf = trim( (string)$pTimeframe );
	switch( $pPeriod ) {
		case 'day':
			if( !ads_roas_date_ok( $tf ) ) {
				return false;
			}
			$since = $until = $tf;
			break;
		case 'week':
			if( !preg_match( '/^(\d{4}) Week (\d{1,2})$/', $tf, $m ) ) {
				return false;
			}
			$ww = (int)$m[2];
			if( $ww < 1 || $ww > 53 ) {
				return false;
			}
			$start = strtotime( $m[1].'-01-01 12:00:00' ) + ( $ww - 1 ) * 86400 * 7;
			$since = date( 'Y-m-d', $start );
			$until = date( 'Y-m-d', $start + 6 * 86400 );
			if( substr( $until, 0, 4 ) !== $m[1] ) {
				$until = $m[1].'-12-31';
			}
			break;
		case 'quarter':
			if( !preg_match( '/^(\d{4})-Q([1-4])$/', $tf, $m ) ) {
				return false;
			}
			$sm = ( (int)$m[2] - 1 ) * 3 + 1;
			$since = sprintf( '%s-%02d-01', $m[1], $sm );
			$until = date( 'Y-m-t', strtotime( sprintf( '%s-%02d-01 12:00:00', $m[1], $sm + 2 ) ) );
			break;
		case 'year':
			if( !preg_match( '/^(\d{4})$/', $tf, $m ) ) {
				return false;
			}
			$since = $m[1].'-01-01';
			$until = $m[1].'-12-31';
			break;
		case 'month':
		default:
			if( !preg_match( '/^(\d{4})-(\d{2})$/', $tf, $m ) || (int)$m[2] < 1 || (int)$m[2] > 12 ) {
				return false;
			}
			$pPeriod = 'month';
			$since = $tf.'-01';
			$until = date( 'Y-m-t', strtotime( $since.' 12:00:00' ) );
			break;
	}
	return array( 'since' => $since, 'until' => $until, 'period' => $pPeriod, 'timeframe' => $tf, 'label' => $tf );
}

function ads_roas_presets() {
	return array(
		'last7'        => 'Last 7 days',
		'last30'       => 'Last 30 days',
		'last90'       => 'Last 90 days',
		'this_month'   => 'This month',
		'last_month'   => 'Last month',
		'this_quarter' => 'This quarter',
		'ytd'          => 'Year to date',
	);
}

function ads_roas_preset_range( $pPreset, $pToday = null ) {
	$today = $pToday ? $pToday : date( 'Y-m-d' );
	$t = strtotime( $today.' 12:00:00' );
	switch( $pPreset ) {
		case 'last7':
			return array( 'since' => date( 'Y-m-d', $t - 6 * 86400 ), 'until' => $today );
		case 'last30':
			return array( 'since' => date( 'Y-m-d', $t - 29 * 86400 ), 'until' => $today );
		case 'last90':
			return array( 'since' => date( 'Y-m-d', $t - 89 * 86400 ), 'until' => $today );
		case 'this_month':
			return array( 'since' => date( 'Y-m-01', $t ), 'until' => $today );
		case 'last_month':
			$lm = strtotime( date( 'Y-m-01', $t ).' -1 month' );
			return array( 'since' => date( 'Y-m-01', $lm ), 'until' => date( 'Y-m-t', $lm ) );
		case 'this_quarter':
			$q = ads_roas_period_range( 'quarter', ads_roas_timeframe_for( 'quarter', $today ) );
			return array( 'since' => $q['since'], 'until' => $today );
		case 'ytd':
			return array( 'since' => date( 'Y-01-01', $t ), 'until' => $today );
	}
	return false;
}

/**
 * Resolve the report range from request parameters. Precedence:
 * period+timeframe, preset, since/until, default last 90 days. `until` is
 * clamped to today. When the range is exactly one period, `period` and
 * `timeframe` are filled so the registration report can be linked.
 */
function ads_roas_range_from_request( $pParameters, $pToday = null ) {
	$today = $pToday ? $pToday : date( 'Y-m-d' );
	$period = BitBase::getParameter( $pParameters, 'period', '' );
	$timeframe = BitBase::getParameter( $pParameters, 'timeframe', '' );
	$preset = BitBase::getParameter( $pParameters, 'preset', '' );
	$since = BitBase::getParameter( $pParameters, 'since', '' );
	$until = BitBase::getParameter( $pParameters, 'until', '' );
	$ret = array( 'since' => null, 'until' => null, 'period' => null, 'timeframe' => null, 'preset' => null, 'source' => 'default', 'error' => null );

	if( $period !== '' && $timeframe === '' && in_array( $period, ads_roas_periods(), true ) ) {
		// Period alone: the period containing `since` (else today).
		$timeframe = ads_roas_timeframe_for( $period, ads_roas_date_ok( $since ) ? $since : $today );
	}
	if( $period !== '' && $timeframe !== '' && in_array( $period, ads_roas_periods(), true ) ) {
		$r = ads_roas_period_range( $period, $timeframe );
		if( $r ) {
			$ret['since'] = $r['since'];
			$ret['until'] = $r['until'];
			$ret['source'] = 'period';
		} else {
			$ret['error'] = 'Unknown period label.';
		}
	}
	if( $ret['since'] === null && $preset !== '' ) {
		$r = ads_roas_preset_range( $preset, $today );
		if( $r ) {
			$ret['since'] = $r['since'];
			$ret['until'] = $r['until'];
			$ret['preset'] = $preset;
			$ret['source'] = 'preset';
		} else {
			$ret['error'] = 'Unknown preset.';
		}
	}
	if( $ret['since'] === null && ( $since !== '' || $until !== '' ) ) {
		if( ads_roas_date_ok( $since ) && ads_roas_date_ok( $until ) ) {
			$ret['since'] = $since;
			$ret['until'] = $until;
			$ret['source'] = 'dates';
		} else {
			$ret['error'] = 'Dates must be Y-m-d.';
		}
	}
	if( $ret['since'] === null ) {
		$r = ads_roas_preset_range( 'last90', $today );
		$ret['since'] = $r['since'];
		$ret['until'] = $r['until'];
		$ret['preset'] = 'last90';
	}
	if( $ret['until'] > $today ) {
		$ret['until'] = $today;
	}
	if( $ret['since'] > $ret['until'] ) {
		$ret['since'] = $ret['until'];
	}
	// Exact period match (for links into the registration report).
	foreach( array( 'day', 'week', 'month', 'quarter', 'year' ) as $p ) {
		$tf = ads_roas_timeframe_for( $p, $ret['since'] );
		$r = ads_roas_period_range( $p, $tf );
		if( $r && $r['since'] === $ret['since'] && $r['until'] === $ret['until'] ) {
			$ret['period'] = $p;
			$ret['timeframe'] = $tf;
			break;
		}
	}
	$ret['days'] = (int)round( ( strtotime( $ret['until'].' 12:00:00' ) - strtotime( $ret['since'].' 12:00:00' ) ) / 86400 ) + 1;
	$ret['display'] = ads_roas_range_display( $ret );
	return $ret;
}

/** Human label for a range: "September 2026", "Q3 2026", "Week 37: Sep 7 – 13, 2026", "Sep 1 – Sep 30, 2026". */
function ads_roas_range_display( $pRange ) {
	$s = strtotime( $pRange['since'].' 12:00:00' );
	$u = strtotime( $pRange['until'].' 12:00:00' );
	$span = ( date( 'Y', $s ) === date( 'Y', $u ) )
		? ( date( 'n', $s ) === date( 'n', $u ) ? date( 'M j', $s ).' – '.date( 'j, Y', $u ) : date( 'M j', $s ).' – '.date( 'M j, Y', $u ) )
		: date( 'M j, Y', $s ).' – '.date( 'M j, Y', $u );
	switch( isset( $pRange['period'] ) ? $pRange['period'] : null ) {
		case 'day':
			return date( 'D M j, Y', $s );
		case 'week':
			return 'Week '.(int)substr( $pRange['timeframe'], -2 ).': '.$span;
		case 'month':
			return date( 'F Y', $s );
		case 'quarter':
			return 'Q'.( intdiv( (int)date( 'n', $s ) - 1, 3 ) + 1 ).' '.date( 'Y', $s );
		case 'year':
			return date( 'Y', $s );
	}
	return $span;
}

/** SQL list literal for cleaned campaign ids, or '' when no filter. */
function ads_roas_id_list_sql( $pIds ) {
	$out = array();
	foreach( (array)$pIds as $id ) {
		$id = ads_roas_id_clean( $id );
		if( $id !== '' ) {
			$out[$id] = "'".$id."'";
		}
	}
	return $out ? '('.implode( ',', $out ).')' : '';
}

// -------------------------------------------------------------- tables

function ads_roas_table_exists( $pDb, $pTable ) {
	static $cache = array();
	if( !isset( $cache[$pTable] ) ) {
		$cache[$pTable] = (bool)$pDb->getOne(
			"SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?",
			array( $pTable )
		);
	}
	return $cache[$pTable];
}

function ads_roas_tables_ready( $pDb ) {
	if( empty( $pDb->mType ) || strpos( $pDb->mType, 'postgres' ) === false ) {
		return false;
	}
	return ads_roas_table_exists( $pDb, 'stats_ad_metrics_daily' ) && ads_roas_table_exists( $pDb, 'stats_ad_network' );
}

function ads_roas_networks( $pDb ) {
	return $pDb->getAssoc( "SELECT network_code, display_name FROM stats_ad_network ORDER BY network_code" );
}

function ads_roas_has_window_cols( $pDb ) {
	return (bool)$pDb->getOne(
		"SELECT 1 FROM information_schema.columns
		  WHERE table_schema = 'public' AND table_name = 'stats_ad_network' AND column_name = 'click_window_days'"
	);
}

function ads_roas_network_row( $pDb, $pNetwork ) {
	if( ads_roas_has_window_cols( $pDb ) ) {
		return $pDb->getRow(
			"SELECT network_code, display_name, timezone, currency, click_window_days, view_window_days, window_source
			   FROM stats_ad_network WHERE network_code = ?",
			array( $pNetwork )
		);
	}
	$row = $pDb->getRow(
		"SELECT network_code, display_name, timezone, currency FROM stats_ad_network WHERE network_code = ?",
		array( $pNetwork )
	);
	if( $row ) {
		$row['click_window_days'] = null;
		$row['view_window_days'] = null;
		$row['window_source'] = null;
	}
	return $row;
}

function ads_roas_save_click_window( $pDb, $pNetwork, $pDays ) {
	$pDays = (int)$pDays;
	if( $pDays < 1 || $pDays > 90 || !ads_roas_has_window_cols( $pDb ) ) {
		return false;
	}
	$pDb->query(
		"UPDATE stats_ad_network SET click_window_days = ?, window_source = 'user' WHERE network_code = ?",
		array( $pDays, $pNetwork )
	);
	return true;
}

// ------------------------------------------------------ revenue source

/**
 * Default revenue source: Bitcommerce paid orders. `orders_sql` must yield
 * order_id, user_id, purchased_at (timestamp), revenue (numeric), currency.
 */
function ads_roas_default_revenue_source( $pDb ) {
	global $gBitSystem;
	$ready = is_object( $gBitSystem ) && $gBitSystem->isPackageActive( 'bitcommerce' );
	return array(
		'label'      => 'Paid orders',
		'ready'      => $ready,
		'orders_sql' => "SELECT o.orders_id AS order_id, o.customers_id AS user_id, o.date_purchased AS purchased_at,
		                        o.order_total AS revenue, o.currency
		                   FROM ".BIT_DB_PREFIX."com_orders o
		                  WHERE o.orders_status_id > 0",
		'bind'       => array(),
	);
}

/**
 * Another package may supply the value side without editing this package:
 * register, in its bit_setup_inc.php,
 *   $gLibertySystem->registerService( 'mypkg', 'mypkg', array(
 *       'stats_revenue_source_function' => 'mypkg_stats_revenue_source' ) );
 * The function takes $pDb and returns array( label, ready, orders_sql, bind )
 * with the column contract of ads_roas_default_revenue_source(). The first
 * registered function that returns a usable hash wins.
 */
function ads_roas_revenue_source( $pDb ) {
	global $gLibertySystem;
	static $src = null;
	if( $src !== null ) {
		return $src;
	}
	if( is_object( $gLibertySystem ) && method_exists( $gLibertySystem, 'getServiceValues' ) ) {
		$fns = $gLibertySystem->getServiceValues( 'stats_revenue_source_function' );
		if( is_array( $fns ) ) {
			foreach( $fns as $fn ) {
				if( is_string( $fn ) && function_exists( $fn ) ) {
					$r = call_user_func( $fn, $pDb );
					if( is_array( $r ) && !empty( $r['orders_sql'] ) ) {
						$src = array_merge( array( 'label' => 'Orders', 'ready' => true, 'bind' => array() ), $r );
						return $src;
					}
				}
			}
		}
	}
	$src = ads_roas_default_revenue_source( $pDb );
	return $src;
}

function ads_roas_commerce_ready() {
	global $gBitSystem;
	if( !is_object( $gBitSystem ) ) {
		return false;
	}
	$src = ads_roas_revenue_source( $gBitSystem->mDb );
	return !empty( $src['ready'] );
}

// --------------------------------------------------------- assumptions

/** Page assumptions in stats_prefs. break_even_roas = 100 / margin %. */
function ads_roas_assumptions( $pDb ) {
	$margin = stats_pref_get( 'roas_gross_margin_pct', null );
	$desired = stats_pref_get( 'roas_desired_commerce_roas', null );
	$margin = ( $margin !== null && is_numeric( $margin ) && (float)$margin > 0 ) ? (float)$margin : null;
	$desired = ( $desired !== null && is_numeric( $desired ) && (float)$desired > 0 ) ? (float)$desired : null;
	return array(
		'gross_margin_pct'      => $margin,
		'break_even_roas'       => $margin ? 100.0 / $margin : null,
		'desired_commerce_roas' => $desired,
	);
}

/** @return string|true error text or true */
function ads_roas_save_assumptions( $pDb, $pParameters ) {
	$margin = trim( (string)BitBase::getParameter( $pParameters, 'gross_margin_pct', '' ) );
	$desired = trim( (string)BitBase::getParameter( $pParameters, 'desired_commerce_roas', '' ) );
	if( $margin !== '' && ( !is_numeric( $margin ) || (float)$margin <= 0 || (float)$margin > 100 ) ) {
		return 'Gross margin must be a percentage between 0 and 100.';
	}
	if( $desired !== '' && ( !is_numeric( $desired ) || (float)$desired <= 0 ) ) {
		return 'Desired commerce ROAS must be a positive number (for example 3.5).';
	}
	stats_pref_set( 'roas_gross_margin_pct', $margin === '' ? null : (string)(float)$margin );
	stats_pref_set( 'roas_desired_commerce_roas', $desired === '' ? null : (string)(float)$desired );
	return true;
}

// ------------------------------------------------------------- queries

/** Campaign ids are network ids; keep only the characters they contain. */
function ads_roas_id_clean( $v ) {
	return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string)$v );
}

function ads_roas_num( $v ) {
	return ( $v === null || $v === '' ) ? null : (float)$v;
}

function ads_roas_div( $a, $b ) {
	return ( $b !== null && (float)$b > 0 ) ? (float)$a / (float)$b : null;
}

/** Spend, network metrics and the spend-weighted target per campaign. */
function ads_roas_spend_rows( $pDb, $pNetwork, $pSince, $pUntil, $pCampaignIds = array() ) {
	$list = ads_roas_id_list_sql( $pCampaignIds );
	$campFilter = $list ? "AND m.campaign_id IN $list" : '';
	$hasSettings = ads_roas_table_exists( $pDb, 'stats_ad_campaign_settings_daily' );
	$settingsJoin = '';
	$targetExpr = 'c.target_roas';
	$historyExpr = '0';
	$reasonsExpr = 'false';
	if( $hasSettings ) {
		$settingsJoin = "LEFT JOIN LATERAL (
			SELECT s.target_roas, s.snapshot_date, s.primary_status_reasons
			  FROM stats_ad_campaign_settings_daily s
			 WHERE s.network_code = m.network_code AND s.account_id = m.account_id
			   AND s.campaign_id = m.campaign_id AND s.snapshot_date <= m.metric_date
			 ORDER BY s.snapshot_date DESC LIMIT 1) s ON true";
		$targetExpr = 's.target_roas';
		$historyExpr = "SUM(m.spend) FILTER (WHERE s.snapshot_date IS NULL)";
		$reasonsExpr = "BOOL_OR(s.primary_status_reasons @> ARRAY['BUDGET_CONSTRAINED']::text[])";
	}
	return $pDb->getAll(
		"SELECT m.campaign_id,
		        COALESCE(c.campaign_name, m.campaign_id) AS campaign_name,
		        c.channel, c.status, c.primary_status,
		        c.bidding_strategy AS bidding_strategy_type,
		        c.target_roas AS current_target_roas, c.target_cpa, c.budget_amount,
		        SUM(m.spend) AS spend, SUM(m.clicks) AS clicks, SUM(m.impressions) AS impressions,
		        SUM(m.network_conversions) AS network_conversions,
		        SUM(m.network_value) AS network_value,
		        SUM(m.conversions_by_conv_date) AS conversions_by_conv_date,
		        SUM(m.value_by_conv_date) AS value_by_conv_date,
		        SUM(m.all_conversions_value) AS all_conversions_value,
		        SUM(m.impressions / NULLIF(m.search_is, 0)) AS eligible_impr,
		        SUM(m.impressions / NULLIF(m.search_is, 0) * m.search_budget_lost_is) AS budget_lost_impr,
		        SUM(m.impressions / NULLIF(m.search_is, 0) * m.search_rank_lost_is) AS rank_lost_impr,
		        SUM(m.spend * $targetExpr) AS spend_x_target,
		        SUM(m.spend) FILTER (WHERE $targetExpr IS NOT NULL) AS spend_with_target,
		        MIN($targetExpr) AS target_min, MAX($targetExpr) AS target_max,
		        COUNT(DISTINCT $targetExpr) AS target_variants,
		        $historyExpr AS spend_without_history,
		        $reasonsExpr AS budget_constrained,
		        MAX(m.pulled_at) AS pulled_at
		   FROM stats_ad_metrics_daily m
		   LEFT JOIN stats_ad_campaign c
		     ON c.network_code = m.network_code AND c.account_id = m.account_id AND c.campaign_id = m.campaign_id
		   $settingsJoin
		  WHERE m.grain = 'campaign' AND m.network_code = ?
		    AND m.metric_date >= ?::date AND m.metric_date <= ?::date
		    $campFilter
		  GROUP BY m.campaign_id, c.campaign_name, c.channel, c.status, c.primary_status,
		           c.bidding_strategy, c.target_roas, c.target_cpa, c.budget_amount
		  ORDER BY SUM(m.spend) DESC",
		array( $pNetwork, $pSince, $pUntil )
	);
}

/** Latest settings row per campaign (scope, strategy name, budget, reasons). */
function ads_roas_latest_settings( $pDb, $pNetwork ) {
	if( !ads_roas_table_exists( $pDb, 'stats_ad_campaign_settings_daily' ) ) {
		return array();
	}
	$rows = $pDb->getAll(
		"SELECT DISTINCT ON (campaign_id) campaign_id, snapshot_date, bidding_strategy_type, bidding_scope,
		        bidding_strategy_name, target_roas, target_cpa, budget_amount, budget_delivery,
		        primary_status, primary_status_reasons
		   FROM stats_ad_campaign_settings_daily
		  WHERE network_code = ?
		  ORDER BY campaign_id, snapshot_date DESC",
		array( $pNetwork )
	);
	$ret = array();
	foreach( $rows as $r ) {
		$ret[(string)$r['campaign_id']] = $r;
	}
	return $ret;
}

/** Period / cohort / LTV revenue per campaign from order attribution. */
function ads_roas_revenue_rows( $pDb, $pNetwork, $pSince, $pUntil, $pSource, $pClickDays, $pCampaignIds = array() ) {
	$days = $pClickDays ? (int)$pClickDays : 0;
	$list = ads_roas_id_list_sql( $pCampaignIds );
	$oaFilter = $list ? "AND oa.campaign_id IN $list" : '';
	$aFilter = $list ? "AND a.campaign_id IN $list" : '';
	$bind = array_merge(
		$pSource['bind'],
		array( $pNetwork, $pNetwork, $pSince, $pUntil, $pSince, $pUntil, $days, $days, $days )
	);
	$rows = $pDb->getAll(
		"WITH orders AS ( ".$pSource['orders_sql']." ),
		att AS (
		   SELECT oa.orders_id, oa.campaign_id
		     FROM stats_ad_order_attribution oa
		    WHERE oa.network_code = ? AND oa.campaign_id IS NOT NULL $oaFilter
		),
		ft AS (
		   SELECT a.user_id, a.campaign_id,
		          COALESCE(a.click_date::timestamp, to_timestamp(u.registration_date)::timestamp) AS first_touch_at
		     FROM stats_ad_user_attribution a
		     JOIN ".BIT_DB_PREFIX."users_users u ON u.user_id = a.user_id
		    WHERE a.network_code = ? AND a.campaign_id IS NOT NULL $aFilter
		),
		rev AS (
		   SELECT att.campaign_id, o.order_id, o.user_id, o.revenue, o.purchased_at, ft.first_touch_at,
		          (o.purchased_at >= ?::timestamp AND o.purchased_at < (?::date + 1)) AS in_period,
		          (ft.first_touch_at IS NOT NULL
		           AND ft.first_touch_at >= ?::timestamp AND ft.first_touch_at < (?::date + 1)
		           AND o.purchased_at >= ft.first_touch_at) AS in_cohort
		     FROM orders o
		     JOIN att ON att.orders_id = o.order_id
		     LEFT JOIN ft ON ft.user_id = o.user_id AND ft.campaign_id = att.campaign_id
		)
		SELECT campaign_id,
		       COALESCE(SUM(revenue) FILTER (WHERE in_period), 0) AS period_revenue,
		       COUNT(*) FILTER (WHERE in_period) AS period_orders,
		       COUNT(DISTINCT user_id) FILTER (WHERE in_period) AS period_buyers,
		       COALESCE(SUM(revenue) FILTER (WHERE in_cohort AND purchased_at < first_touch_at + (?::int * interval '1 day')), 0) AS cohort_revenue,
		       COUNT(*) FILTER (WHERE in_cohort AND purchased_at < first_touch_at + (?::int * interval '1 day')) AS cohort_orders,
		       COUNT(DISTINCT user_id) FILTER (WHERE in_cohort AND purchased_at < first_touch_at + (?::int * interval '1 day')) AS cohort_buyers,
		       COALESCE(SUM(revenue) FILTER (WHERE in_cohort), 0) AS cohort_ltv,
		       COUNT(*) FILTER (WHERE in_cohort) AS ltv_orders
		  FROM rev
		 GROUP BY campaign_id",
		$bind
	);
	$ret = array();
	foreach( $rows as $r ) {
		$ret[(string)$r['campaign_id']] = $r + array( 'cohort_users' => 0 );
	}
	// Cohort size (first touch in range) also for campaigns with no orders.
	$users = $pDb->getAll(
		"SELECT a.campaign_id, COUNT(*) AS cohort_users
		   FROM stats_ad_user_attribution a
		   JOIN ".BIT_DB_PREFIX."users_users u ON u.user_id = a.user_id
		  WHERE a.network_code = ? AND a.campaign_id IS NOT NULL $aFilter
		    AND COALESCE(a.click_date::timestamp, to_timestamp(u.registration_date)::timestamp) >= ?::timestamp
		    AND COALESCE(a.click_date::timestamp, to_timestamp(u.registration_date)::timestamp) < (?::date + 1)
		  GROUP BY a.campaign_id",
		array( $pNetwork, $pSince, $pUntil )
	);
	foreach( $users as $u ) {
		$id = (string)$u['campaign_id'];
		if( !isset( $ret[$id] ) ) {
			$ret[$id] = ads_roas_rev_blank();
		}
		$ret[$id]['cohort_users'] = (int)$u['cohort_users'];
	}
	return $ret;
}

function ads_roas_rev_blank() {
	return array(
		'period_revenue' => 0, 'period_orders' => 0, 'period_buyers' => 0,
		'cohort_revenue' => 0, 'cohort_orders' => 0, 'cohort_buyers' => 0,
		'cohort_ltv' => 0, 'ltv_orders' => 0, 'cohort_users' => 0,
	);
}

/**
 * Where every paid order in the range lands: this network by id, by name
 * only, untracked paid, other networks, organic, or no first touch at all.
 * Sums to the install's paid order total for the range.
 */
function ads_roas_reconciliation( $pDb, $pNetwork, $pSince, $pUntil, $pSource ) {
	$bind = array_merge( $pSource['bind'], array( $pNetwork, $pNetwork, $pNetwork, $pSince, $pUntil ) );
	$rows = $pDb->getAll(
		"WITH orders AS ( ".$pSource['orders_sql']." )
		SELECT CASE
		         WHEN oa.orders_id IS NULL THEN 'no_first_touch'
		         WHEN oa.network_code = ? AND oa.campaign_id IS NOT NULL THEN 'network_by_id'
		         WHEN oa.network_code = ? AND (oa.extra->>'untracked_paid') = 'true' THEN 'network_untracked_paid'
		         WHEN oa.network_code = ? THEN 'network_name_only'
		         WHEN oa.network_code IS NOT NULL THEN 'other_network:' || oa.network_code
		         ELSE 'organic_none' END AS bucket,
		       COUNT(*) AS orders, COALESCE(SUM(o.revenue), 0) AS revenue, COUNT(DISTINCT o.user_id) AS buyers
		  FROM orders o
		  LEFT JOIN stats_ad_order_attribution oa ON oa.orders_id = o.order_id
		 WHERE o.purchased_at >= ?::timestamp AND o.purchased_at < (?::date + 1)
		 GROUP BY 1",
		$bind
	);
	$by = array();
	$total = array( 'orders' => 0, 'revenue' => 0.0, 'buyers' => 0 );
	foreach( $rows as $r ) {
		$by[$r['bucket']] = $r;
		$total['orders'] += (int)$r['orders'];
		$total['revenue'] += (float)$r['revenue'];
		$total['buyers'] += (int)$r['buyers'];
	}
	$order = array(
		'network_by_id'          => 'Attributed to a campaign',
		'network_name_only'      => 'Tracking label only (no campaign id)',
		'network_untracked_paid' => 'Paid click, no campaign',
	);
	$others = array();
	foreach( $by as $k => $r ) {
		if( strpos( $k, 'other_network:' ) === 0 ) {
			$others[$k] = 'Other network: '.substr( $k, 14 );
		}
	}
	ksort( $others );
	$order = $order + $others + array(
		'organic_none'   => 'Organic or direct first touch',
		'no_first_touch' => 'No first touch stored',
	);
	$out = array();
	foreach( $order as $k => $label ) {
		$r = isset( $by[$k] ) ? $by[$k] : array( 'orders' => 0, 'revenue' => 0, 'buyers' => 0 );
		$out[] = array(
			'bucket'        => $k,
			'label'         => $label,
			'is_network'    => strpos( $k, 'network_' ) === 0,
			'orders'        => (int)$r['orders'],
			'revenue'       => (float)$r['revenue'],
			'buyers'        => (int)$r['buyers'],
			'revenue_share' => ads_roas_div( $r['revenue'], $total['revenue'] ),
			'orders_share'  => ads_roas_div( $r['orders'], $total['orders'] ),
		);
	}
	return array( 'rows' => $out, 'total' => $total );
}

/** Blend the historical spend-weighted target with the current one. */
function ads_roas_effective_target( $pSpend ) {
	$spendWith = (float)$pSpend['spend_with_target'];
	$spendX = (float)$pSpend['spend_x_target'];
	$noHist = (float)$pSpend['spend_without_history'];
	$current = ads_roas_num( $pSpend['current_target_roas'] );
	$source = null;
	if( $noHist > 0 && $current !== null ) {
		$spendX += $noHist * $current;
		$spendWith += $noHist;
		$source = $spendWith > $noHist ? 'mixed' : 'current';
	} elseif( $spendWith > 0 ) {
		$source = 'history';
	}
	$target = $spendWith > 0 ? $spendX / $spendWith : null;
	if( $target === null && $current !== null ) {
		$target = $current;
		$source = 'current';
	}
	return array( $target, $source );
}

/** One display row: network metrics, books, gap and flags. */
function ads_roas_compute_row( $pId, $pName, $pSpend, $pRev, $pLatest, $pAssumptions, $pClickDays ) {
	$spend = (float)$pSpend['spend'];
	$clicks = (int)$pSpend['clicks'];
	$impressions = (int)$pSpend['impressions'];
	$networkValue = (float)$pSpend['network_value'];
	$valueConvDate = ads_roas_num( $pSpend['value_by_conv_date'] );
	$periodRevenue = (float)$pRev['period_revenue'];
	$cohortRevenue = (float)$pRev['cohort_revenue'];
	$cohortLtv = (float)$pRev['cohort_ltv'];
	list( $target, $targetSource ) = ads_roas_effective_target( $pSpend );
	$eligible = ads_roas_num( $pSpend['eligible_impr'] );
	$searchIs = ( $eligible && $eligible > 0 ) ? $impressions / $eligible : null;
	$budgetLost = ( $eligible && $eligible > 0 ) ? (float)$pSpend['budget_lost_impr'] / $eligible : null;
	$rankLost = ( $eligible && $eligible > 0 ) ? (float)$pSpend['rank_lost_impr'] / $eligible : null;
	$reasons = isset( $pLatest['primary_status_reasons'] ) ? (string)$pLatest['primary_status_reasons'] : '';
	$budgetLimited = ( $budgetLost !== null && $budgetLost >= 0.10 )
		|| !empty( $pSpend['budget_constrained'] ) && $pSpend['budget_constrained'] !== 'f'
		|| strpos( $reasons, 'BUDGET_CONSTRAINED' ) !== false;
	$commerceRoas = ads_roas_div( $periodRevenue, $spend );
	$cohortRoas = $pClickDays ? ads_roas_div( $cohortRevenue, $spend ) : null;
	$valueRatio = ads_roas_div( $networkValue, $cohortRevenue );
	$valueRatioPeriod = ( $valueConvDate !== null ) ? ads_roas_div( $valueConvDate, $periodRevenue ) : null;
	$desired = $pAssumptions['desired_commerce_roas'];
	$suggested = ( $desired && $valueRatio ) ? $desired * $valueRatio : null;
	$flags = array();
	if( $budgetLimited ) {
		$flags[] = 'budget_limited';
	}
	if( $targetSource === 'current' || $targetSource === 'mixed' ) {
		$flags[] = 'target_current';
	}
	if( isset( $pSpend['channel'] ) && $pSpend['channel'] === 'PERFORMANCE_MAX' ) {
		$flags[] = 'pmax';
	}
	if( $spend <= 0 ) {
		$flags[] = 'no_spend';
	}
	if( $target === null ) {
		$flags[] = 'no_target';
	}
	$vsTarget = null;
	if( $target !== null && $commerceRoas !== null ) {
		$vsTarget = $commerceRoas >= $target ? 'above' : 'below';
	}
	return array(
		'campaign_id'              => $pId,
		'campaign_name'            => $pName,
		'channel'                  => isset( $pSpend['channel'] ) ? $pSpend['channel'] : null,
		'status'                   => isset( $pSpend['status'] ) ? $pSpend['status'] : null,
		'primary_status'           => isset( $pLatest['primary_status'] ) ? $pLatest['primary_status'] : ( isset( $pSpend['primary_status'] ) ? $pSpend['primary_status'] : null ),
		'bidding_strategy_type'    => isset( $pSpend['bidding_strategy_type'] ) ? $pSpend['bidding_strategy_type'] : null,
		'bidding_scope'            => isset( $pLatest['bidding_scope'] ) ? $pLatest['bidding_scope'] : null,
		'bidding_strategy_name'    => isset( $pLatest['bidding_strategy_name'] ) ? $pLatest['bidding_strategy_name'] : null,
		'budget_amount'            => ads_roas_num( isset( $pSpend['budget_amount'] ) ? $pSpend['budget_amount'] : null ),
		'target_cpa'               => ads_roas_num( isset( $pSpend['target_cpa'] ) ? $pSpend['target_cpa'] : null ),
		// network
		'spend'                    => $spend,
		'clicks'                   => $clicks,
		'impressions'              => $impressions,
		'cpc'                      => ads_roas_div( $spend, $clicks ),
		'network_conversions'      => ads_roas_num( $pSpend['network_conversions'] ),
		'network_value'            => $networkValue,
		'network_roas'             => ads_roas_div( $networkValue, $spend ),
		'conversions_by_conv_date' => ads_roas_num( $pSpend['conversions_by_conv_date'] ),
		'value_by_conv_date'       => $valueConvDate,
		'network_roas_conv_date'   => $valueConvDate !== null ? ads_roas_div( $valueConvDate, $spend ) : null,
		'all_conversions_value'    => ads_roas_num( $pSpend['all_conversions_value'] ),
		'target_roas'              => $target,
		'target_source'            => $targetSource,
		'current_target_roas'      => ads_roas_num( $pSpend['current_target_roas'] ),
		'target_min'               => ads_roas_num( $pSpend['target_min'] ),
		'target_max'               => ads_roas_num( $pSpend['target_max'] ),
		'target_variants'          => (int)$pSpend['target_variants'],
		'search_is'                => $searchIs,
		'budget_lost_is'           => $budgetLost,
		'rank_lost_is'             => $rankLost,
		'budget_limited'           => $budgetLimited,
		// books
		'period_revenue'           => $periodRevenue,
		'period_orders'            => (int)$pRev['period_orders'],
		'period_buyers'            => (int)$pRev['period_buyers'],
		'commerce_roas'            => $commerceRoas,
		'aov'                      => ads_roas_div( $periodRevenue, $pRev['period_orders'] ),
		'cohort_users'             => (int)$pRev['cohort_users'],
		'cohort_revenue'           => $cohortRevenue,
		'cohort_orders'            => (int)$pRev['cohort_orders'],
		'cohort_buyers'            => (int)$pRev['cohort_buyers'],
		'cohort_roas'              => $cohortRoas,
		'cohort_ltv'               => $cohortLtv,
		'ltv_orders'               => (int)$pRev['ltv_orders'],
		'ltv_roas'                 => ads_roas_div( $cohortLtv, $spend ),
		'cac'                      => ads_roas_div( $spend, $pRev['cohort_buyers'] ),
		'cost_per_user'            => ads_roas_div( $spend, $pRev['cohort_users'] ),
		// gap
		'value_ratio'              => $valueRatio,
		'value_ratio_period'       => $valueRatioPeriod,
		'suggested_target'         => $suggested,
		'vs_target'                => $vsTarget,
		'target_delta'             => ( $target !== null && $commerceRoas !== null ) ? $commerceRoas - $target : null,
		'flags'                    => $flags,
	);
}

function ads_roas_totals( $pRows, $pAssumptions, $pClickDays ) {
	$t = array(
		'spend' => 0.0, 'clicks' => 0, 'impressions' => 0, 'network_conversions' => 0.0, 'network_value' => 0.0,
		'conversions_by_conv_date' => 0.0, 'value_by_conv_date' => 0.0, 'all_conversions_value' => 0.0,
		'period_revenue' => 0.0, 'period_orders' => 0, 'period_buyers' => 0,
		'cohort_users' => 0, 'cohort_revenue' => 0.0, 'cohort_orders' => 0, 'cohort_buyers' => 0,
		'cohort_ltv' => 0.0, 'ltv_orders' => 0,
	);
	$spendX = 0.0;
	$spendWith = 0.0;
	$budgetLimited = 0;
	foreach( $pRows as $r ) {
		foreach( array_keys( $t ) as $k ) {
			$t[$k] += (float)$r[$k];
		}
		if( $r['target_roas'] !== null ) {
			$spendX += $r['spend'] * $r['target_roas'];
			$spendWith += $r['spend'];
		}
		if( $r['budget_limited'] ) {
			$budgetLimited++;
		}
	}
	foreach( array( 'clicks', 'impressions', 'period_orders', 'period_buyers', 'cohort_users', 'cohort_orders', 'cohort_buyers', 'ltv_orders' ) as $k ) {
		$t[$k] = (int)$t[$k];
	}
	$t['cpc'] = ads_roas_div( $t['spend'], $t['clicks'] );
	$t['network_roas'] = ads_roas_div( $t['network_value'], $t['spend'] );
	$t['network_roas_conv_date'] = ads_roas_div( $t['value_by_conv_date'], $t['spend'] );
	$t['commerce_roas'] = ads_roas_div( $t['period_revenue'], $t['spend'] );
	$t['cohort_roas'] = $pClickDays ? ads_roas_div( $t['cohort_revenue'], $t['spend'] ) : null;
	$t['ltv_roas'] = ads_roas_div( $t['cohort_ltv'], $t['spend'] );
	$t['aov'] = ads_roas_div( $t['period_revenue'], $t['period_orders'] );
	$t['cac'] = ads_roas_div( $t['spend'], $t['cohort_buyers'] );
	$t['target_roas'] = $spendWith > 0 ? $spendX / $spendWith : null;
	$t['value_ratio'] = ads_roas_div( $t['network_value'], $t['cohort_revenue'] );
	$t['value_ratio_period'] = ads_roas_div( $t['value_by_conv_date'], $t['period_revenue'] );
	$desired = $pAssumptions['desired_commerce_roas'];
	$t['suggested_target'] = ( $desired && $t['value_ratio'] ) ? $desired * $t['value_ratio'] : null;
	$t['budget_limited_campaigns'] = $budgetLimited;
	$t['vs_target'] = ( $t['target_roas'] !== null && $t['commerce_roas'] !== null ) ? ( $t['commerce_roas'] >= $t['target_roas'] ? 'above' : 'below' ) : null;
	return $t;
}

function ads_roas_freshness( $pDb, $pNetwork ) {
	$r = $pDb->getRow(
		"SELECT MAX(pulled_at) AS pulled_at, MAX(metric_date) AS metric_date
		   FROM stats_ad_metrics_daily WHERE grain = 'campaign' AND network_code = ?",
		array( $pNetwork )
	);
	return is_array( $r ) ? $r : array( 'pulled_at' => null, 'metric_date' => null );
}

/** Session timezone vs the account timezone; a mismatch shifts day totals. */
function ads_roas_tz_warning( $pDb, $pNetwork ) {
	$session = $pDb->getOne( "SELECT current_setting('TimeZone')" );
	$acct = $pDb->getOne( "SELECT timezone FROM stats_ad_account WHERE network_code = ? AND timezone IS NOT NULL", array( $pNetwork ) );
	if( $session && $acct && strcasecmp( $session, $acct ) !== 0 ) {
		return array( 'session' => $session, 'account' => $acct );
	}
	return null;
}

/**
 * @param object $pDb BitDb
 * @param string $pSince Y-m-d inclusive
 * @param string $pUntil Y-m-d inclusive
 * @param array  $pOpts  network (default google), series (bool, default true),
 *                       campaign_ids (list; empty = all campaigns)
 */
function ads_roas_report( $pDb, $pSince, $pUntil, $pOpts = array() ) {
	$network = !empty( $pOpts['network'] ) ? $pOpts['network'] : 'google';
	$campaignIds = !empty( $pOpts['campaign_ids'] ) ? (array)$pOpts['campaign_ids'] : array();
	$source = ads_roas_revenue_source( $pDb );
	$wantRev = !empty( $source['ready'] ) && ads_roas_table_exists( $pDb, 'stats_ad_order_attribution' );
	$netRow = ads_roas_network_row( $pDb, $network );
	if( !is_array( $netRow ) ) {
		$netRow = array();
	}
	$clickDays = ( !empty( $netRow['click_window_days'] ) ) ? (int)$netRow['click_window_days'] : null;
	$netLabel = !empty( $netRow['display_name'] ) ? $netRow['display_name'] : $network;
	$assumptions = ads_roas_assumptions( $pDb );

	$spendRows = ads_roas_spend_rows( $pDb, $network, $pSince, $pUntil, $campaignIds );
	$latest = ads_roas_latest_settings( $pDb, $network );
	$rev = $wantRev ? ads_roas_revenue_rows( $pDb, $network, $pSince, $pUntil, $source, $clickDays, $campaignIds ) : array();
	$names = array();

	$rows = array();
	foreach( $spendRows as $s ) {
		$id = (string)$s['campaign_id'];
		$r = isset( $rev[$id] ) ? $rev[$id] : ads_roas_rev_blank();
		unset( $rev[$id] );
		$lt = isset( $latest[$id] ) ? $latest[$id] : array();
		$rows[] = ads_roas_compute_row( $id, $s['campaign_name'], $s, $r, $lt, $assumptions, $clickDays );
	}
	// Campaigns with revenue in the range but no spend rows (paused, or spend outside the range).
	if( $rev ) {
		$ids = array_keys( $rev );
		$nameRows = $pDb->getAll(
			"SELECT campaign_id, campaign_name, channel, status, bidding_strategy, target_roas, target_cpa, budget_amount
			   FROM stats_ad_campaign WHERE network_code = ? AND campaign_id IN ('".implode( "','", array_map( 'ads_roas_id_clean', $ids ) )."')",
			array( $network )
		);
		foreach( $nameRows as $nr ) {
			$names[(string)$nr['campaign_id']] = $nr;
		}
		foreach( $rev as $id => $r ) {
			if( (float)$r['period_revenue'] == 0 && (float)$r['cohort_ltv'] == 0 && (int)$r['cohort_users'] == 0 ) {
				continue;
			}
			$nr = isset( $names[$id] ) ? $names[$id] : array();
			$emptySpend = array(
				'spend' => 0, 'clicks' => 0, 'impressions' => 0, 'network_conversions' => null, 'network_value' => 0,
				'conversions_by_conv_date' => null, 'value_by_conv_date' => null, 'all_conversions_value' => null,
				'eligible_impr' => null, 'budget_lost_impr' => null, 'rank_lost_impr' => null,
				'spend_x_target' => 0, 'spend_with_target' => 0, 'spend_without_history' => 0,
				'target_min' => null, 'target_max' => null, 'target_variants' => 0, 'budget_constrained' => false,
				'current_target_roas' => isset( $nr['target_roas'] ) ? $nr['target_roas'] : null,
				'target_cpa' => isset( $nr['target_cpa'] ) ? $nr['target_cpa'] : null,
				'budget_amount' => isset( $nr['budget_amount'] ) ? $nr['budget_amount'] : null,
				'channel' => isset( $nr['channel'] ) ? $nr['channel'] : null,
				'status' => isset( $nr['status'] ) ? $nr['status'] : null,
				'bidding_strategy_type' => isset( $nr['bidding_strategy'] ) ? $nr['bidding_strategy'] : null,
			);
			$lt = isset( $latest[$id] ) ? $latest[$id] : array();
			$name = !empty( $nr['campaign_name'] ) ? $nr['campaign_name'] : $id;
			$rows[] = ads_roas_compute_row( $id, $name, $emptySpend, $r, $lt, $assumptions, $clickDays );
		}
	}

	$totals = ads_roas_totals( $rows, $assumptions, $clickDays );
	$recon = $wantRev ? ads_roas_reconciliation( $pDb, $network, $pSince, $pUntil, $source ) : null;
	$fresh = ads_roas_freshness( $pDb, $network );
	$immatureFrom = null;
	if( $clickDays && !empty( $fresh['metric_date'] ) ) {
		$immatureFrom = date( 'Y-m-d', strtotime( $fresh['metric_date'].' 12:00:00' ) - $clickDays * 86400 );
	}
	$series = null;
	if( !isset( $pOpts['series'] ) || $pOpts['series'] ) {
		$series = ads_roas_series( $pDb, $network, $pSince, $pUntil, $wantRev ? $source : null, null, $campaignIds );
	}

	return array(
		'since'             => $pSince,
		'until'             => $pUntil,
		'network'           => $network,
		'network_label'     => $netLabel,
		'campaign_ids'      => $campaignIds,
		'currency'          => !empty( $netRow['currency'] ) ? $netRow['currency'] : null,
		'click_window_days' => $clickDays,
		'window_source'     => isset( $netRow['window_source'] ) ? $netRow['window_source'] : null,
		'revenue_source'    => $source['label'],
		'revenue_ready'     => $wantRev,
		'assumptions'       => $assumptions,
		'rows'              => $rows,
		'totals'            => $totals,
		'reconciliation'    => $recon,
		'series'            => $series,
		'pulled_at'         => $fresh['pulled_at'],
		'last_metric_date'  => $fresh['metric_date'],
		'immature_from'     => $immatureFrom,
		'tz_warning'        => ads_roas_tz_warning( $pDb, $network ),
	);
}

// -------------------------------------------------------------- series

function ads_roas_series_bucket( $pSince, $pUntil ) {
	$days = (int)round( ( strtotime( $pUntil.' 12:00:00' ) - strtotime( $pSince.' 12:00:00' ) ) / 86400 ) + 1;
	if( $days <= 42 ) {
		return 'day';
	}
	if( $days <= 400 ) {
		return 'week';
	}
	return 'month';
}

/**
 * Spend, network value and attributed period revenue per bucket, with the
 * spend-weighted target. Rows carry commerce_roas / network_roas / target_roas.
 */
function ads_roas_series( $pDb, $pNetwork, $pSince, $pUntil, $pSource = null, $pBucket = null, $pCampaignIds = array() ) {
	$bucket = $pBucket ? $pBucket : ads_roas_series_bucket( $pSince, $pUntil );
	$list = ads_roas_id_list_sql( $pCampaignIds );
	$mFilter = $list ? "AND m.campaign_id IN $list" : '';
	$oaFilter = $list ? "AND oa.campaign_id IN $list" : '';
	$hasSettings = ads_roas_table_exists( $pDb, 'stats_ad_campaign_settings_daily' );
	$settingsJoin = '';
	$targetExpr = 'c.target_roas';
	if( $hasSettings ) {
		$settingsJoin = "LEFT JOIN LATERAL (
			SELECT s.target_roas FROM stats_ad_campaign_settings_daily s
			 WHERE s.network_code = m.network_code AND s.account_id = m.account_id
			   AND s.campaign_id = m.campaign_id AND s.snapshot_date <= m.metric_date
			 ORDER BY s.snapshot_date DESC LIMIT 1) s ON true";
		$targetExpr = 'COALESCE(s.target_roas, c.target_roas)';
	}
	$spend = $pDb->getAll(
		"SELECT date_trunc(?, m.metric_date)::date AS b,
		        SUM(m.spend) AS spend, SUM(m.clicks) AS clicks,
		        SUM(m.network_value) AS network_value, SUM(m.value_by_conv_date) AS value_by_conv_date,
		        SUM(m.spend * $targetExpr) AS spend_x_target,
		        SUM(m.spend) FILTER (WHERE $targetExpr IS NOT NULL) AS spend_with_target
		   FROM stats_ad_metrics_daily m
		   LEFT JOIN stats_ad_campaign c
		     ON c.network_code = m.network_code AND c.account_id = m.account_id AND c.campaign_id = m.campaign_id
		   $settingsJoin
		  WHERE m.grain = 'campaign' AND m.network_code = ?
		    AND m.metric_date >= ?::date AND m.metric_date <= ?::date
		    $mFilter
		  GROUP BY 1 ORDER BY 1",
		array( $bucket, $pNetwork, $pSince, $pUntil )
	);
	$byBucket = array();
	foreach( $spend as $s ) {
		$byBucket[$s['b']] = array(
			'bucket'            => $s['b'],
			'spend'             => (float)$s['spend'],
			'clicks'            => (int)$s['clicks'],
			'network_value'     => (float)$s['network_value'],
			'value_by_conv_date'=> ads_roas_num( $s['value_by_conv_date'] ),
			'revenue'           => 0.0,
			'orders'            => 0,
			'target_roas'       => ads_roas_div( $s['spend_x_target'], $s['spend_with_target'] ),
		);
	}
	if( $pSource ) {
		$bind = array_merge( $pSource['bind'], array( $bucket, $pNetwork, $pSince, $pUntil ) );
		$rev = $pDb->getAll(
			"WITH orders AS ( ".$pSource['orders_sql']." )
			SELECT date_trunc(?, o.purchased_at)::date AS b, SUM(o.revenue) AS revenue, COUNT(*) AS orders
			  FROM orders o
			  JOIN stats_ad_order_attribution oa ON oa.orders_id = o.order_id
			 WHERE oa.network_code = ? AND oa.campaign_id IS NOT NULL $oaFilter
			   AND o.purchased_at >= ?::timestamp AND o.purchased_at < (?::date + 1)
			 GROUP BY 1 ORDER BY 1",
			$bind
		);
		foreach( $rev as $r ) {
			if( !isset( $byBucket[$r['b']] ) ) {
				$byBucket[$r['b']] = array(
					'bucket' => $r['b'], 'spend' => 0.0, 'clicks' => 0, 'network_value' => 0.0,
					'value_by_conv_date' => null, 'revenue' => 0.0, 'orders' => 0, 'target_roas' => null,
				);
			}
			$byBucket[$r['b']]['revenue'] = (float)$r['revenue'];
			$byBucket[$r['b']]['orders'] = (int)$r['orders'];
		}
	}
	ksort( $byBucket );
	$out = array();
	foreach( $byBucket as $b ) {
		$b['commerce_roas'] = ads_roas_div( $b['revenue'], $b['spend'] );
		$b['network_roas'] = ads_roas_div( $b['network_value'], $b['spend'] );
		$out[] = $b;
	}
	return array( 'bucket' => $bucket, 'rows' => $out );
}

function ads_roas_nice_step( $pMax ) {
	if( $pMax <= 0 ) {
		return 1;
	}
	$raw = $pMax / 4;
	$pow = pow( 10, floor( log10( $raw ) ) );
	foreach( array( 1, 2, 2.5, 5, 10 ) as $m ) {
		if( $m * $pow >= $raw ) {
			return $m * $pow;
		}
	}
	return 10 * $pow;
}

function ads_roas_bucket_label( $pDate, $pBucket ) {
	$ts = strtotime( $pDate.' 12:00:00' );
	if( $pBucket === 'month' ) {
		return date( 'M Y', $ts );
	}
	return date( 'M j', $ts );
}

/**
 * Inline SVG: commerce ROAS, network ROAS and the target per bucket, with
 * the break-even line and a band over buckets still inside the click
 * window. Colors come from stats.css classes (.roas-svg). Each bucket has
 * a native <title> tooltip; the legend and table view live in the template.
 */
function ads_roas_svg_series( $pSeries, $pOpts = array() ) {
	$rows = isset( $pSeries['rows'] ) ? $pSeries['rows'] : array();
	$bucket = isset( $pSeries['bucket'] ) ? $pSeries['bucket'] : 'week';
	$n = count( $rows );
	if( $n === 0 ) {
		return '';
	}
	$w = 800; $h = 260; $ml = 52; $mr = 72; $mt = 16; $mb = 34;
	$pw = $w - $ml - $mr; $ph = $h - $mt - $mb;
	$breakEven = isset( $pOpts['break_even'] ) ? $pOpts['break_even'] : null;
	$immature = isset( $pOpts['immature_from'] ) ? $pOpts['immature_from'] : null;
	$seriesDef = array(
		'commerce_roas' => array( 'class' => 's-commerce', 'label' => isset( $pOpts['label_commerce'] ) ? $pOpts['label_commerce'] : 'Commerce' ),
		'network_roas'  => array( 'class' => 's-network',  'label' => isset( $pOpts['label_network'] ) ? $pOpts['label_network'] : 'Network' ),
		'target_roas'   => array( 'class' => 's-target',   'label' => isset( $pOpts['label_target'] ) ? $pOpts['label_target'] : 'Target' ),
	);
	$max = $breakEven ? (float)$breakEven : 0;
	foreach( $rows as $r ) {
		foreach( array_keys( $seriesDef ) as $k ) {
			if( $r[$k] !== null && $r[$k] > $max ) {
				$max = $r[$k];
			}
		}
	}
	if( $max <= 0 ) {
		$max = 1;
	}
	$step = ads_roas_nice_step( $max );
	$top = ceil( $max / $step ) * $step;
	if( $top <= 0 ) {
		$top = $step;
	}
	$x = function( $i ) use ( $n, $ml, $pw ) {
		return $n > 1 ? $ml + $i * ( $pw / ( $n - 1 ) ) : $ml + $pw / 2;
	};
	$y = function( $v ) use ( $mt, $ph, $top ) {
		return $mt + $ph - ( $v / $top ) * $ph;
	};
	$f = function( $v ) {
		return number_format( $v, 1, '.', '' );
	};
	$e = function( $s ) {
		return htmlspecialchars( (string)$s, ENT_QUOTES, 'UTF-8' );
	};
	$svg = '<svg class="roas-svg" viewBox="0 0 '.$w.' '.$h.'" role="img" aria-label="'.$e( 'ROAS by '.$bucket ).'">';
	// Immature band.
	if( $immature ) {
		$start = null;
		foreach( $rows as $i => $r ) {
			if( $r['bucket'] >= $immature ) {
				$start = $i;
				break;
			}
		}
		if( $start !== null ) {
			$bx = $n > 1 ? $x( $start ) - ( $pw / ( $n - 1 ) ) / 2 : $ml;
			$bx = max( $bx, $ml );
			$svg .= '<rect class="band" x="'.$f( $bx ).'" y="'.$mt.'" width="'.$f( $ml + $pw - $bx ).'" height="'.$ph.'" />';
			$svg .= '<text class="lbl band-lbl" x="'.$f( $ml + $pw - 4 ).'" y="'.( $mt + 12 ).'" text-anchor="end">'.$e( 'conversions still maturing' ).'</text>';
		}
	}
	// Gridlines and y labels.
	for( $v = 0; $v <= $top + 1e-9; $v += $step ) {
		$yy = $y( $v );
		$svg .= '<line class="grid" x1="'.$ml.'" y1="'.$f( $yy ).'" x2="'.( $ml + $pw ).'" y2="'.$f( $yy ).'" />';
		$svg .= '<text class="lbl" x="'.( $ml - 8 ).'" y="'.$f( $yy + 4 ).'" text-anchor="end">'.$e( rtrim( rtrim( number_format( $v, 2, '.', '' ), '0' ), '.' ).'x' ).'</text>';
	}
	$svg .= '<line class="axis" x1="'.$ml.'" y1="'.$f( $mt + $ph ).'" x2="'.( $ml + $pw ).'" y2="'.$f( $mt + $ph ).'" />';
	// X labels: at most 8; the last bucket is always labeled and a regular
	// label too close to it is dropped so the two never overlap.
	$every = max( 1, (int)ceil( $n / 8 ) );
	foreach( $rows as $i => $r ) {
		$regular = ( $i % $every === 0 ) && ( $n - 1 - $i >= max( 1, (int)ceil( $every / 2 ) ) );
		if( $regular || $i === $n - 1 ) {
			$svg .= '<text class="lbl" x="'.$f( $x( $i ) ).'" y="'.( $mt + $ph + 18 ).'" text-anchor="middle">'.$e( ads_roas_bucket_label( $r['bucket'], $bucket ) ).'</text>';
		}
	}
	// Break-even reference.
	if( $breakEven && $breakEven <= $top ) {
		$yy = $y( $breakEven );
		$svg .= '<line class="ref" x1="'.$ml.'" y1="'.$f( $yy ).'" x2="'.( $ml + $pw ).'" y2="'.$f( $yy ).'" />';
		$svg .= '<text class="lbl ref-lbl" x="'.( $ml + $pw + 6 ).'" y="'.$f( $yy + 4 ).'">'.$e( 'break-even '.rtrim( rtrim( number_format( $breakEven, 2, '.', '' ), '0' ), '.' ).'x' ).'</text>';
	}
	// Series lines (segments break on null).
	$ends = array();
	foreach( $seriesDef as $k => $def ) {
		$pts = array();
		$segments = array();
		$last = null;
		foreach( $rows as $i => $r ) {
			if( $r[$k] === null ) {
				if( $pts ) {
					$segments[] = $pts;
					$pts = array();
				}
				continue;
			}
			$pts[] = $f( $x( $i ) ).','.$f( $y( min( $r[$k], $top ) ) );
			$last = array( 'i' => $i, 'v' => $r[$k] );
		}
		if( $pts ) {
			$segments[] = $pts;
		}
		foreach( $segments as $seg ) {
			if( count( $seg ) === 1 ) {
				list( $px, $py ) = explode( ',', $seg[0] );
				$svg .= '<circle class="'.$def['class'].' dot" cx="'.$px.'" cy="'.$py.'" r="4" />';
			} else {
				$svg .= '<polyline class="'.$def['class'].'" points="'.implode( ' ', $seg ).'" />';
			}
		}
		if( $last !== null ) {
			$ends[] = array( 'class' => $def['class'], 'label' => $def['label'], 'x' => $x( $last['i'] ), 'y' => $y( min( $last['v'], $top ) ), 'v' => $last['v'] );
		}
	}
	// End dots and labels, pushed apart when they collide.
	usort( $ends, function( $a, $b ) { return $a['y'] <=> $b['y']; } );
	$prev = null;
	foreach( $ends as $i => $end ) {
		$ly = $end['y'];
		if( $prev !== null && $ly - $prev < 14 ) {
			$ly = $prev + 14;
		}
		$ends[$i]['ly'] = $ly;
		$prev = $ly;
	}
	foreach( $ends as $end ) {
		$svg .= '<circle class="'.$end['class'].' dot" cx="'.$f( $end['x'] ).'" cy="'.$f( $end['y'] ).'" r="4" />';
		if( abs( $end['ly'] - $end['y'] ) > 1 ) {
			$svg .= '<line class="leader" x1="'.$f( $end['x'] + 4 ).'" y1="'.$f( $end['y'] ).'" x2="'.$f( $ml + $pw + 4 ).'" y2="'.$f( $end['ly'] ).'" />';
		}
		$svg .= '<text class="lbl end-lbl" x="'.( $ml + $pw + 8 ).'" y="'.$f( $end['ly'] + 4 ).'">'.$e( $end['label'].' '.number_format( $end['v'], 2 ).'x' ).'</text>';
	}
	// Hover targets with native tooltips.
	$slot = $n > 1 ? $pw / ( $n - 1 ) : $pw;
	foreach( $rows as $i => $r ) {
		$hx = $n > 1 ? $x( $i ) - $slot / 2 : $ml;
		$hx = max( $hx, $ml );
		$hw = min( $slot, $ml + $pw - $hx );
		$tip = ads_roas_bucket_label( $r['bucket'], $bucket )
			.': spend '.number_format( $r['spend'], 0 )
			.' · '.$seriesDef['commerce_roas']['label'].' '.( $r['commerce_roas'] === null ? '—' : number_format( $r['commerce_roas'], 2 ).'x' )
			.' · '.$seriesDef['network_roas']['label'].' '.( $r['network_roas'] === null ? '—' : number_format( $r['network_roas'], 2 ).'x' )
			.' · '.$seriesDef['target_roas']['label'].' '.( $r['target_roas'] === null ? '—' : number_format( $r['target_roas'], 2 ).'x' );
		$svg .= '<rect class="hit" x="'.$f( $hx ).'" y="'.$mt.'" width="'.$f( $hw ).'" height="'.$ph.'"><title>'.$e( $tip ).'</title></rect>';
	}
	$svg .= '</svg>';
	return $svg;
}

// ---------------------------------------------------------- drill-down

function ads_roas_campaign_detail( $pDb, $pNetwork, $pCampaignId, $pSince, $pUntil, $pSource, $pClickDays ) {
	$id = (string)$pCampaignId;
	$camp = $pDb->getRow(
		"SELECT campaign_id, campaign_name, channel, status, primary_status, bidding_strategy, bidding_strategy_id,
		        target_roas, target_cpa, budget_amount, extra
		   FROM stats_ad_campaign WHERE network_code = ? AND campaign_id = ?",
		array( $pNetwork, $id )
	);
	$ret = array( 'campaign' => $camp ? $camp : array( 'campaign_id' => $id, 'campaign_name' => $id ) );

	// Daily: metrics plus attributed revenue by purchase day.
	$revSql = "SELECT NULL::date AS d, 0::numeric AS revenue, 0 AS orders WHERE false";
	$bind = array( $pSince, $pUntil, $pNetwork, $id );
	if( $pSource ) {
		$revSql = "WITH orders AS ( ".$pSource['orders_sql']." )
			SELECT o.purchased_at::date AS d, SUM(o.revenue) AS revenue, COUNT(*) AS orders
			  FROM orders o JOIN stats_ad_order_attribution oa ON oa.orders_id = o.order_id
			 WHERE oa.network_code = ? AND oa.campaign_id = ?
			   AND o.purchased_at >= ?::timestamp AND o.purchased_at < (?::date + 1)
			 GROUP BY 1";
		$bind = array_merge( $bind, $pSource['bind'], array( $pNetwork, $id, $pSince, $pUntil ) );
	}
	$ret['daily'] = $pDb->getAll(
		"SELECT d.d AS metric_date, m.spend, m.clicks, m.impressions, m.network_conversions, m.network_value,
		        m.conversions_by_conv_date, m.value_by_conv_date, m.search_is, m.search_budget_lost_is, m.search_rank_lost_is,
		        COALESCE(rev.revenue, 0) AS revenue, COALESCE(rev.orders, 0) AS orders
		   FROM generate_series(?::date, ?::date, interval '1 day') AS d(d)
		   LEFT JOIN stats_ad_metrics_daily m
		     ON m.metric_date = d.d AND m.grain = 'campaign' AND m.network_code = ? AND m.campaign_id = ?
		   LEFT JOIN ( $revSql ) rev ON rev.d = d.d
		  ORDER BY d.d",
		$bind
	);

	// Ad groups (asset groups for PMax) with attributed orders where the landing or click named one.
	$agRev = array();
	if( $pSource ) {
		// Orders carry an ad group id (click or gad_adgroupid) or only a
		// tracking name; names are matched to the campaign's ad groups.
		$agRows = $pDb->getAll(
			"WITH orders AS ( ".$pSource['orders_sql']." )
			SELECT COALESCE(NULLIF(oa.adgroup_id, ''), g.adgroup_id, '') AS adgroup_id,
			       SUM(o.revenue) AS revenue, COUNT(*) AS orders
			  FROM orders o
			  JOIN stats_ad_order_attribution oa ON oa.orders_id = o.order_id
			  LEFT JOIN stats_ad_adgroup g
			    ON g.network_code = oa.network_code AND g.campaign_id = oa.campaign_id
			   AND NULLIF(oa.adgroup_id, '') IS NULL AND oa.adgroup_name IS NOT NULL
			   AND lower(g.adgroup_name) = lower(oa.adgroup_name)
			 WHERE oa.network_code = ? AND oa.campaign_id = ?
			   AND o.purchased_at >= ?::timestamp AND o.purchased_at < (?::date + 1)
			 GROUP BY 1",
			array_merge( $pSource['bind'], array( $pNetwork, $id, $pSince, $pUntil ) )
		);
		foreach( $agRows as $a ) {
			$agRev[$a['adgroup_id']] = $a;
		}
	}
	$adgroups = $pDb->getAll(
		"SELECT m.adgroup_id, COALESCE(g.adgroup_name, m.adgroup_id) AS adgroup_name, g.adgroup_kind, g.status,
		        SUM(m.spend) AS spend, SUM(m.clicks) AS clicks, SUM(m.impressions) AS impressions,
		        SUM(m.network_conversions) AS network_conversions, SUM(m.network_value) AS network_value,
		        SUM(m.value_by_conv_date) AS value_by_conv_date
		   FROM stats_ad_metrics_daily m
		   LEFT JOIN stats_ad_adgroup g
		     ON g.network_code = m.network_code AND g.account_id = m.account_id AND g.campaign_id = m.campaign_id AND g.adgroup_id = m.adgroup_id
		  WHERE m.grain = 'adgroup' AND m.network_code = ? AND m.campaign_id = ?
		    AND m.metric_date >= ?::date AND m.metric_date <= ?::date
		  GROUP BY m.adgroup_id, g.adgroup_name, g.adgroup_kind, g.status
		  ORDER BY SUM(m.spend) DESC",
		array( $pNetwork, $id, $pSince, $pUntil )
	);
	foreach( $adgroups as $i => $g ) {
		$r = isset( $agRev[$g['adgroup_id']] ) ? $agRev[$g['adgroup_id']] : array( 'revenue' => 0, 'orders' => 0 );
		unset( $agRev[$g['adgroup_id']] );
		$adgroups[$i]['revenue'] = (float)$r['revenue'];
		$adgroups[$i]['orders'] = (int)$r['orders'];
		$adgroups[$i]['commerce_roas'] = ads_roas_div( $r['revenue'], $g['spend'] );
		$adgroups[$i]['network_roas'] = ads_roas_div( $g['network_value'], $g['spend'] );
	}
	$unassigned = array( 'revenue' => 0.0, 'orders' => 0 );
	foreach( $agRev as $a ) {
		$unassigned['revenue'] += (float)$a['revenue'];
		$unassigned['orders'] += (int)$a['orders'];
	}
	$ret['adgroups'] = $adgroups;
	$ret['adgroup_unassigned'] = $unassigned;

	// Conversion actions the network credited to this campaign in the range.
	$ret['conversion_actions'] = array();
	if( ads_roas_table_exists( $pDb, 'stats_ad_conversion_daily' ) ) {
		$ret['conversion_actions'] = $pDb->getAll(
			"SELECT conversion_action_id, MAX(conversion_action_name) AS conversion_action_name,
			        MAX(conversion_action_category) AS category,
			        SUM(conversions) AS conversions, SUM(conversions_value) AS conversions_value,
			        SUM(all_conversions) AS all_conversions, SUM(all_conversions_value) AS all_conversions_value,
			        SUM(conversions_by_conv_date) AS conversions_by_conv_date, SUM(value_by_conv_date) AS value_by_conv_date
			   FROM stats_ad_conversion_daily
			  WHERE network_code = ? AND campaign_id = ? AND metric_date >= ?::date AND metric_date <= ?::date
			  GROUP BY conversion_action_id
			  ORDER BY SUM(conversions_value) DESC NULLS LAST",
			array( $pNetwork, $id, $pSince, $pUntil )
		);
	}

	// Attributed orders in the range (period view) and cohort orders after it.
	$ret['orders'] = array();
	if( $pSource ) {
		$days = $pClickDays ? (int)$pClickDays : 0;
		$ret['orders'] = $pDb->getAll(
			"WITH orders AS ( ".$pSource['orders_sql']." ),
			ft AS (
			   SELECT a.user_id, COALESCE(a.click_date::timestamp, to_timestamp(u.registration_date)::timestamp) AS first_touch_at,
			          a.source, a.click_date, a.adgroup_name, a.keyword_text
			     FROM stats_ad_user_attribution a JOIN ".BIT_DB_PREFIX."users_users u ON u.user_id = a.user_id
			    WHERE a.network_code = ? AND a.campaign_id = ?
			)
			SELECT o.order_id, o.user_id, o.purchased_at, o.revenue, o.currency,
			       ft.first_touch_at, ft.source, ft.click_date, ft.adgroup_name, ft.keyword_text,
			       (o.purchased_at >= ?::timestamp AND o.purchased_at < (?::date + 1)) AS in_period,
			       (ft.first_touch_at >= ?::timestamp AND ft.first_touch_at < (?::date + 1)
			        AND o.purchased_at >= ft.first_touch_at
			        AND o.purchased_at < ft.first_touch_at + (?::int * interval '1 day')) AS in_cohort
			  FROM orders o
			  JOIN stats_ad_order_attribution oa ON oa.orders_id = o.order_id
			  LEFT JOIN ft ON ft.user_id = o.user_id
			 WHERE oa.network_code = ? AND oa.campaign_id = ?
			   AND (
			     (o.purchased_at >= ?::timestamp AND o.purchased_at < (?::date + 1))
			     OR (ft.first_touch_at >= ?::timestamp AND ft.first_touch_at < (?::date + 1)
			         AND o.purchased_at >= ft.first_touch_at
			         AND o.purchased_at < ft.first_touch_at + (?::int * interval '1 day'))
			   )
			 ORDER BY o.purchased_at DESC
			 LIMIT 500",
			array_merge( $pSource['bind'], array(
				$pNetwork, $id,
				$pSince, $pUntil, $pSince, $pUntil, $days,
				$pNetwork, $id,
				$pSince, $pUntil, $pSince, $pUntil, $days,
			) )
		);
	}

	// Settings history collapsed to change points.
	$ret['target_history'] = array();
	if( ads_roas_table_exists( $pDb, 'stats_ad_campaign_settings_daily' ) ) {
		$hist = $pDb->getAll(
			"SELECT snapshot_date, status, primary_status, primary_status_reasons, bidding_strategy_type, bidding_scope,
			        bidding_strategy_name, target_roas, target_cpa, budget_amount
			   FROM stats_ad_campaign_settings_daily
			  WHERE network_code = ? AND campaign_id = ?
			  ORDER BY snapshot_date",
			array( $pNetwork, $id )
		);
		$prevKey = null;
		foreach( $hist as $hrow ) {
			$key = implode( '|', array(
				$hrow['status'], $hrow['primary_status'], $hrow['bidding_strategy_type'], $hrow['bidding_scope'],
				$hrow['target_roas'], $hrow['target_cpa'], $hrow['budget_amount'],
			) );
			if( $key !== $prevKey ) {
				$ret['target_history'][] = $hrow;
				$prevKey = $key;
			}
		}
		$ret['target_history'] = array_reverse( $ret['target_history'] );
	}
	return $ret;
}

// ----------------------------------------------------- referrer report

/**
 * Lifetime and in-range revenue per user, one grouped query per 1,000 ids.
 * Used by the registration report instead of one query per user.
 */
function ads_roas_user_revenue_map( $pDb, $pUserIds, $pSince, $pUntil, $pSource = null ) {
	if( $pSource === null ) {
		$pSource = ads_roas_revenue_source( $pDb );
	}
	$ret = array();
	if( empty( $pSource['ready'] ) || !$pUserIds ) {
		return $ret;
	}
	$ids = array();
	foreach( $pUserIds as $u ) {
		$u = (int)$u;
		if( $u > 0 ) {
			$ids[$u] = $u;
		}
	}
	foreach( array_chunk( array_values( $ids ), 1000 ) as $chunk ) {
		$rows = $pDb->getAll(
			"WITH orders AS ( ".$pSource['orders_sql']." )
			SELECT user_id, SUM(revenue) AS total_revenue, COUNT(*) AS total_orders,
			       COALESCE(SUM(revenue) FILTER (WHERE purchased_at >= ?::timestamp AND purchased_at < (?::date + 1)), 0) AS period_revenue,
			       COUNT(*) FILTER (WHERE purchased_at >= ?::timestamp AND purchased_at < (?::date + 1)) AS period_orders
			  FROM orders
			 WHERE user_id IN (".implode( ',', $chunk ).")
			 GROUP BY user_id",
			array_merge( $pSource['bind'], array( $pSince, $pUntil, $pSince, $pUntil ) )
		);
		foreach( $rows as $r ) {
			$ret[(int)$r['user_id']] = array(
				'total_revenue'  => (float)$r['total_revenue'],
				'total_orders'   => (int)$r['total_orders'],
				'period_revenue' => (float)$r['period_revenue'],
				'period_orders'  => (int)$r['period_orders'],
			);
		}
	}
	return $ret;
}

// ----------------------------------------------------------------- CSV

/** key => header; one spec for headers and lines. */
function ads_roas_csv_columns() {
	return array(
		'campaign_id'              => 'campaign_id',
		'campaign_name'            => 'campaign_name',
		'channel'                  => 'channel',
		'status'                   => 'status',
		'bidding_strategy_type'    => 'bidding_strategy',
		'bidding_scope'            => 'bidding_scope',
		'budget_amount'            => 'daily_budget',
		'spend'                    => 'spend',
		'clicks'                   => 'clicks',
		'impressions'              => 'impressions',
		'cpc'                      => 'cpc',
		'network_conversions'      => 'network_conversions',
		'network_value'            => 'network_value_click_dated',
		'network_roas'             => 'network_roas',
		'conversions_by_conv_date' => 'network_conversions_conv_dated',
		'value_by_conv_date'       => 'network_value_conv_dated',
		'network_roas_conv_date'   => 'network_roas_conv_dated',
		'target_roas'              => 'target_roas_weighted',
		'target_source'            => 'target_source',
		'current_target_roas'      => 'target_roas_current',
		'target_cpa'               => 'target_cpa',
		'search_is'                => 'search_impression_share',
		'budget_lost_is'           => 'search_budget_lost_is',
		'rank_lost_is'             => 'search_rank_lost_is',
		'budget_limited'           => 'budget_limited',
		'period_orders'            => 'period_orders',
		'period_buyers'            => 'period_buyers',
		'period_revenue'           => 'period_revenue',
		'commerce_roas'            => 'commerce_roas',
		'aov'                      => 'aov',
		'cohort_users'             => 'cohort_users',
		'cohort_buyers'            => 'cohort_buyers',
		'cohort_orders'            => 'cohort_orders',
		'cohort_revenue'           => 'cohort_revenue',
		'cohort_roas'              => 'cohort_roas',
		'cohort_ltv'               => 'cohort_ltv',
		'ltv_roas'                 => 'ltv_roas',
		'cac'                      => 'cac',
		'value_ratio'              => 'value_ratio_click_dated',
		'value_ratio_period'       => 'value_ratio_conv_dated',
		'suggested_target'         => 'suggested_network_target',
		'vs_target'                => 'commerce_vs_target',
		'flags'                    => 'flags',
	);
}

function ads_roas_csv_headers() {
	return array_values( ads_roas_csv_columns() );
}

function ads_roas_csv_line( $pRow ) {
	$out = array();
	foreach( array_keys( ads_roas_csv_columns() ) as $k ) {
		$v = isset( $pRow[$k] ) ? $pRow[$k] : null;
		if( is_array( $v ) ) {
			$out[] = implode( ' ', $v );
		} elseif( is_bool( $v ) ) {
			$out[] = $v ? '1' : '0';
		} elseif( $v === null ) {
			$out[] = '';
		} elseif( is_float( $v ) ) {
			$out[] = sprintf( '%.4f', $v );
		} else {
			$out[] = $v;
		}
	}
	return $out;
}
