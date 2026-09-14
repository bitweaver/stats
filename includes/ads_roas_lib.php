<?php
/**
 * First-party ROAS queries.
 *
 * Cost is warehouse spend (any network): SUM(stats_ad_metrics_daily.spend).
 * Value is this install's Bitcommerce paid totals: SUM(com_orders.order_total)
 * through stats_ad_order_attribution. That is Commerce ROAS. Network conversions_value
 * / spend is {Google,Microsoft,…} ROAS for comparison (partial attribution).
 * Click-through conversion window is stored on stats_ad_network; if unknown, ask.
 *
 * Warehouse tables are optional. Bitcommerce is the only revenue source;
 * without it, spend still reports and value is zero. No ad-network writes.
 */

function ads_roas_date_ok( $pDate ) {
	if( !is_string( $pDate ) || !preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $pDate, $m ) ) {
		return false;
	}
	return checkdate( (int)$m[2], (int)$m[3], (int)$m[1] );
}

function ads_roas_tables_ready( $pDb ) {
	if( empty( $pDb->mType ) || strpos( $pDb->mType, 'postgres' ) === false ) {
		return false;
	}
	$n = $pDb->getOne( "SELECT to_regclass('public.stats_ad_metrics_daily')" );
	return !empty( $n );
}

function ads_roas_commerce_ready() {
	global $gBitSystem;
	return is_object( $gBitSystem ) && $gBitSystem->isPackageActive( 'bitcommerce' );
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
			"SELECT network_code, display_name, click_window_days, view_window_days, window_source
			   FROM stats_ad_network WHERE network_code = ?",
			array( $pNetwork )
		);
	}
	$row = $pDb->getRow(
		"SELECT network_code, display_name FROM stats_ad_network WHERE network_code = ?",
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

function ads_roas_rev_blank() {
	return array(
		'revenue' => 0, 'orders' => 0, 'buyers' => 0,
		'lookback_revenue' => 0, 'lookback_orders' => 0, 'lookback_buyers' => 0,
	);
}

/**
 * @param object $pDb BitDb
 * @param string $pSince Y-m-d inclusive
 * @param string $pUntil Y-m-d inclusive
 * @param array  $pOpts  network (default google), cohort (bool)
 * @return array{since,until,network,cohort,rows,totals}
 */
function ads_roas_report( $pDb, $pSince, $pUntil, $pOpts = array() ) {
	$network = !empty( $pOpts['network'] ) ? $pOpts['network'] : 'google';
	$cohort = !empty( $pOpts['cohort'] );
	$wantRev = ads_roas_commerce_ready();
	$netRow = ads_roas_network_row( $pDb, $network );
	if( !is_array( $netRow ) ) {
		$netRow = array();
	}
	$clickDays = ( !empty( $netRow['click_window_days'] ) ) ? (int)$netRow['click_window_days'] : null;
	$netLabel = !empty( $netRow['display_name'] ) ? $netRow['display_name'] : $network;

	$spendRows = $pDb->getAll(
		"SELECT m.campaign_id, COALESCE(c.campaign_name, m.campaign_id) AS campaign_name,
		        MAX(c.target_roas) AS target_roas,
		        SUM(m.spend) AS spend, SUM(m.clicks) AS clicks, SUM(m.impressions) AS impressions,
		        SUM(m.network_value) AS network_value
		 FROM stats_ad_metrics_daily m
		 LEFT JOIN stats_ad_campaign c
		   ON c.network_code = m.network_code AND c.account_id = m.account_id AND c.campaign_id = m.campaign_id
		 WHERE m.grain = 'campaign' AND m.network_code = ?
		   AND m.metric_date >= ?::date AND m.metric_date <= ?::date
		 GROUP BY m.campaign_id, COALESCE(c.campaign_name, m.campaign_id)
		 ORDER BY SUM(m.spend) DESC",
		array( $network, $pSince, $pUntil )
	);

	$rev = array();
	if( $wantRev ) {
		$revRows = $pDb->getAll(
			"SELECT a.campaign_id,
			        SUM(o.order_total) AS revenue,
			        COUNT(*) AS orders,
			        COUNT(DISTINCT o.customers_id) AS buyers
			 FROM stats_ad_order_attribution a
			 JOIN com_orders o ON o.orders_id = a.orders_id
			 JOIN users_users u ON u.user_id = a.user_id
			 WHERE o.orders_status_id > 0
			   AND o.date_purchased >= ?::timestamp
			   AND o.date_purchased < (?::date + 1)
			   AND to_timestamp(u.registration_date) >= ?::timestamp
			   AND to_timestamp(u.registration_date) < (?::date + 1)
			   AND a.campaign_id IS NOT NULL
			   AND a.network_code = ?
			 GROUP BY a.campaign_id",
			array( $pSince, $pUntil, $pSince, $pUntil, $network )
		);
		foreach( $revRows as $r ) {
			$rev[(string)$r['campaign_id']] = $r;
		}
	}

	$lookback = array();
	if( $wantRev && $clickDays ) {
		$lbRows = $pDb->getAll(
			"SELECT a.campaign_id,
			        SUM(o.order_total) AS lookback_revenue,
			        COUNT(*) AS lookback_orders,
			        COUNT(DISTINCT o.customers_id) AS lookback_buyers
			 FROM stats_ad_order_attribution a
			 JOIN com_orders o ON o.orders_id = a.orders_id
			 JOIN users_users u ON u.user_id = a.user_id
			 WHERE o.orders_status_id > 0
			   AND to_timestamp(u.registration_date) >= ?::timestamp
			   AND to_timestamp(u.registration_date) < (?::date + 1)
			   AND o.date_purchased >= to_timestamp(u.registration_date)
			   AND o.date_purchased <= to_timestamp(u.registration_date) + (?::int * interval '1 day')
			   AND a.campaign_id IS NOT NULL
			   AND a.network_code = ?
			 GROUP BY a.campaign_id",
			array( $pSince, $pUntil, $clickDays, $network )
		);
		foreach( $lbRows as $r ) {
			$lookback[(string)$r['campaign_id']] = $r;
		}
	}

	$cohortMap = array();
	if( $cohort && $wantRev ) {
		$sinceTs = strtotime( $pSince.' UTC' );
		$untilTs = strtotime( $pUntil.' 23:59:59 UTC' );
		$cRows = $pDb->getAll(
			"SELECT a.campaign_id,
			        COUNT(*) AS regs,
			        COUNT(*) FILTER (WHERE EXISTS (
			            SELECT 1 FROM com_orders o WHERE o.customers_id = a.user_id AND o.orders_status_id > 0
			        )) AS buyers,
			        COALESCE(SUM((
			            SELECT SUM(o.order_total) FROM com_orders o
			            WHERE o.customers_id = a.user_id AND o.orders_status_id > 0
			        )), 0) AS ltv
			 FROM stats_ad_user_attribution a
			 JOIN users_users u ON u.user_id = a.user_id
			 WHERE a.campaign_id IS NOT NULL
			   AND a.network_code = ?
			   AND u.registration_date >= ? AND u.registration_date <= ?
			 GROUP BY a.campaign_id",
			array( $network, $sinceTs, $untilTs )
		);
		foreach( $cRows as $r ) {
			$cohortMap[(string)$r['campaign_id']] = $r;
		}
	}

	$rows = array();
	foreach( $spendRows as $s ) {
		$id = (string)$s['campaign_id'];
		$r = isset( $rev[$id] ) ? $rev[$id] : ads_roas_rev_blank();
		unset( $rev[$id] );
		if( isset( $lookback[$id] ) ) {
			$r = array_merge( $r, $lookback[$id] );
			unset( $lookback[$id] );
		}
		$rows[] = ads_roas_row( $id, $s['campaign_name'], $s, $r, $cohort, $cohortMap );
	}

	foreach( $rev as $id => $r ) {
		$name = $id;
		foreach( $spendRows as $s ) {
			if( (string)$s['campaign_id'] === (string)$id ) {
				$name = $s['campaign_name'];
				break;
			}
		}
		if( isset( $lookback[$id] ) ) {
			$r = array_merge( $r, $lookback[$id] );
			unset( $lookback[$id] );
		}
		$emptySpend = array( 'spend' => 0, 'clicks' => 0, 'impressions' => 0, 'network_value' => 0, 'target_roas' => null );
		$rows[] = ads_roas_row( $id, $name, $emptySpend, $r, $cohort, $cohortMap );
	}

	$totals = array(
		'spend' => 0, 'revenue' => 0, 'orders' => 0, 'buyers' => 0,
		'clicks' => 0, 'impressions' => 0, 'network_value' => 0,
		'lookback_revenue' => 0, 'lookback_orders' => 0, 'lookback_buyers' => 0,
		'cohort_regs' => 0, 'cohort_buyers' => 0, 'cohort_ltv' => 0,
	);
	foreach( $rows as $row ) {
		$totals['spend'] += $row['spend'];
		$totals['revenue'] += $row['revenue'];
		$totals['orders'] += $row['orders'];
		$totals['buyers'] += $row['buyers'];
		$totals['clicks'] += $row['clicks'];
		$totals['impressions'] += $row['impressions'];
		$totals['network_value'] += $row['network_value'];
		$totals['lookback_revenue'] += $row['lookback_revenue'];
		$totals['lookback_orders'] += $row['lookback_orders'];
		$totals['lookback_buyers'] += $row['lookback_buyers'];
		$totals['cohort_regs'] += $row['cohort_regs'];
		$totals['cohort_buyers'] += $row['cohort_buyers'];
		$totals['cohort_ltv'] += $row['cohort_ltv'];
	}
	$totals['window_roas'] = $totals['spend'] > 0 ? $totals['revenue'] / $totals['spend'] : null;
	$totals['commerce_roas'] = $totals['window_roas'];
	$totals['lookback_roas'] = $totals['spend'] > 0 ? $totals['lookback_revenue'] / $totals['spend'] : null;
	$totals['remote_roas'] = $totals['spend'] > 0 ? $totals['network_value'] / $totals['spend'] : null;
	$totals['network_roas'] = $totals['remote_roas'];
	$totals['cohort_ltv_roas'] = $totals['spend'] > 0 ? $totals['cohort_ltv'] / $totals['spend'] : null;

	return array(
		'since'             => $pSince,
		'until'             => $pUntil,
		'network'           => $network,
		'network_label'     => $netLabel,
		'click_window_days' => $clickDays,
		'window_source'     => isset( $netRow['window_source'] ) ? $netRow['window_source'] : null,
		'cohort'            => $cohort,
		'rows'              => $rows,
		'totals'            => $totals,
	);
}

function ads_roas_row( $pId, $pName, $pSpend, $pRev, $pCohort, $pCohortMap ) {
	$spend = (float)$pSpend['spend'];
	$revenue = (float)$pRev['revenue'];
	$lookbackRev = isset( $pRev['lookback_revenue'] ) ? (float)$pRev['lookback_revenue'] : 0;
	$target = isset( $pSpend['target_roas'] ) && $pSpend['target_roas'] !== null && $pSpend['target_roas'] !== ''
		? (float)$pSpend['target_roas'] : null;
	$c = ( $pCohort && isset( $pCohortMap[(string)$pId] ) )
		? $pCohortMap[(string)$pId]
		: array( 'regs' => 0, 'buyers' => 0, 'ltv' => 0 );
	$ltv = (float)$c['ltv'];
	$commerceRoas = $spend > 0 ? $revenue / $spend : null;
	$remoteRoas = $spend > 0 ? (float)$pSpend['network_value'] / $spend : null;
	return array(
		'campaign_id'      => $pId,
		'campaign_name'    => $pName,
		'spend'            => $spend,
		'revenue'          => $revenue,
		'orders'           => (int)$pRev['orders'],
		'buyers'           => (int)$pRev['buyers'],
		'clicks'           => (int)$pSpend['clicks'],
		'impressions'      => (int)$pSpend['impressions'],
		'network_value'    => (float)$pSpend['network_value'],
		'target_roas'      => $target,
		'lookback_revenue' => $lookbackRev,
		'lookback_orders'  => isset( $pRev['lookback_orders'] ) ? (int)$pRev['lookback_orders'] : 0,
		'lookback_buyers'  => isset( $pRev['lookback_buyers'] ) ? (int)$pRev['lookback_buyers'] : 0,
		'window_roas'      => $commerceRoas,
		'commerce_roas'    => $commerceRoas,
		'lookback_roas'    => $spend > 0 ? $lookbackRev / $spend : null,
		'remote_roas'      => $remoteRoas,
		'network_roas'     => $remoteRoas,
		'cohort_regs'      => (int)$c['regs'],
		'cohort_buyers'    => (int)$c['buyers'],
		'cohort_ltv'       => $ltv,
		'cohort_ltv_roas'  => $spend > 0 ? $ltv / $spend : null,
	);
}

function ads_roas_csv_headers( $pCohort, $pClickDays = null ) {
	$headers = array(
		'campaign_id', 'campaign_name', 'window_spend', 'commerce_revenue', 'commerce_orders',
		'commerce_buyers', 'commerce_roas', 'target_roas', 'network_value', 'network_roas',
		'click_window_days', 'commerce_lookback_revenue', 'commerce_lookback_roas', 'network_roas_is_partial',
	);
	if( $pCohort ) {
		$headers = array_merge( $headers, array( 'cohort_regs', 'cohort_buyers', 'cohort_ltv', 'cohort_ltv_roas' ) );
	}
	return $headers;
}

function ads_roas_csv_line( $pRow, $pCohort, $pClickDays = null ) {
	$roas = $pRow['commerce_roas'];
	$remote = $pRow['network_roas'];
	$lb = $pRow['lookback_roas'];
	$line = array(
		$pRow['campaign_id'],
		$pRow['campaign_name'],
		sprintf( '%.2f', $pRow['spend'] ),
		sprintf( '%.2f', $pRow['revenue'] ),
		(int)$pRow['orders'],
		(int)$pRow['buyers'],
		is_numeric( $roas ) ? sprintf( '%.3f', $roas ) : '',
		$pRow['target_roas'] !== null ? sprintf( '%.3f', $pRow['target_roas'] ) : '',
		sprintf( '%.2f', $pRow['network_value'] ),
		is_numeric( $remote ) ? sprintf( '%.3f', $remote ) : '',
		$pClickDays !== null ? (int)$pClickDays : '',
		sprintf( '%.2f', $pRow['lookback_revenue'] ),
		is_numeric( $lb ) ? sprintf( '%.3f', $lb ) : '',
		'partial_attribution',
	);
	if( $pCohort ) {
		$cRoas = $pRow['cohort_ltv_roas'];
		$line[] = (int)$pRow['cohort_regs'];
		$line[] = (int)$pRow['cohort_buyers'];
		$line[] = sprintf( '%.2f', $pRow['cohort_ltv'] );
		$line[] = is_numeric( $cRoas ) ? sprintf( '%.3f', $cRoas ) : '';
	}
	return $line;
}
