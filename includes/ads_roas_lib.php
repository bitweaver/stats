<?php
/**
 * First-party ROAS queries.
 *
 * Cost is warehouse spend (any network): SUM(stats_ad_metrics_daily.spend)
 * for metric dates in the report range (the advertiser's click dates).
 * Commerce revenue counts every paid order in the report range for a
 * campaign's first-touch customers, including people who registered before
 * the range. It also counts orders after `until` when the customer
 * registered in the range and the purchase is still inside
 * stats_ad_network.click_window_days of that registration. LTV is those
 * customers' paid orders from the start of the range on, with no day cap.
 * Network conversions_value / spend is {Google,Microsoft,…} ROAS for comparison.
 * If the click window is unknown, ask. Warehouse tables are optional.
 * Bitcommerce is the only revenue source; without it, spend still reports
 * and value is zero. No ad-network writes.
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
		'ltv' => 0,
	);
}

/**
 * @param object $pDb BitDb
 * @param string $pSince Y-m-d inclusive
 * @param string $pUntil Y-m-d inclusive
 * @param array  $pOpts  network (default google)
 * @return array{since,until,network,rows,totals}
 */
function ads_roas_report( $pDb, $pSince, $pUntil, $pOpts = array() ) {
	$network = !empty( $pOpts['network'] ) ? $pOpts['network'] : 'google';
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
		$windowDays = $clickDays ? (int)$clickDays : 0;
		$revRows = $pDb->getAll(
			"SELECT campaign_id,
			        COALESCE(SUM(order_total) FILTER (WHERE in_commerce), 0) AS revenue,
			        COUNT(orders_id) FILTER (WHERE in_commerce) AS orders,
			        COUNT(DISTINCT customers_id) FILTER (WHERE in_commerce) AS buyers,
			        COALESCE(SUM(order_total) FILTER (WHERE date_purchased >= ?::timestamp), 0) AS ltv
			 FROM (
			    SELECT a.campaign_id, o.orders_id, o.order_total, o.date_purchased, a.user_id AS customers_id,
			           (
			             o.orders_id IS NOT NULL AND (
			               (o.date_purchased >= ?::timestamp AND o.date_purchased < (?::date + 1))
			               OR (
			                 ?::int > 0
			                 AND to_timestamp(u.registration_date) >= ?::timestamp
			                 AND to_timestamp(u.registration_date) < (?::date + 1)
			                 AND o.date_purchased >= to_timestamp(u.registration_date)
			                 AND o.date_purchased <= to_timestamp(u.registration_date) + (?::int * interval '1 day')
			               )
			             )
			           ) AS in_commerce
			    FROM stats_ad_user_attribution a
			    JOIN users_users u ON u.user_id = a.user_id
			    LEFT JOIN com_orders o
			      ON o.customers_id = a.user_id AND o.orders_status_id > 0
			    WHERE a.campaign_id IS NOT NULL
			      AND a.network_code = ?
			      AND (
			        (to_timestamp(u.registration_date) >= ?::timestamp
			         AND to_timestamp(u.registration_date) < (?::date + 1))
			        OR EXISTS (
			          SELECT 1 FROM com_orders ox
			          WHERE ox.customers_id = a.user_id
			            AND ox.orders_status_id > 0
			            AND ox.date_purchased >= ?::timestamp
			            AND ox.date_purchased < (?::date + 1)
			        )
			      )
			 ) s
			 GROUP BY campaign_id",
			array(
				$pSince,
				$pSince, $pUntil,
				$windowDays, $pSince, $pUntil, $windowDays,
				$network,
				$pSince, $pUntil,
				$pSince, $pUntil,
			)
		);
		foreach( $revRows as $r ) {
			if( (float)$r['revenue'] == 0 && (float)$r['ltv'] == 0 && (int)$r['orders'] == 0 ) {
				continue;
			}
			$rev[(string)$r['campaign_id']] = $r;
		}
	}

	$rows = array();
	foreach( $spendRows as $s ) {
		$id = (string)$s['campaign_id'];
		$r = isset( $rev[$id] ) ? $rev[$id] : ads_roas_rev_blank();
		unset( $rev[$id] );
		$rows[] = ads_roas_row( $id, $s['campaign_name'], $s, $r );
	}

	foreach( $rev as $id => $r ) {
		$emptySpend = array( 'spend' => 0, 'clicks' => 0, 'impressions' => 0, 'network_value' => 0, 'target_roas' => null );
		$rows[] = ads_roas_row( $id, $id, $emptySpend, $r );
	}

	$totals = array(
		'spend' => 0, 'revenue' => 0, 'orders' => 0, 'buyers' => 0,
		'clicks' => 0, 'impressions' => 0, 'network_value' => 0,
		'ltv' => 0,
	);
	foreach( $rows as $row ) {
		$totals['spend'] += $row['spend'];
		$totals['revenue'] += $row['revenue'];
		$totals['orders'] += $row['orders'];
		$totals['buyers'] += $row['buyers'];
		$totals['clicks'] += $row['clicks'];
		$totals['impressions'] += $row['impressions'];
		$totals['network_value'] += $row['network_value'];
		$totals['ltv'] += $row['ltv'];
	}
	$totals['commerce_roas'] = $totals['spend'] > 0 ? $totals['revenue'] / $totals['spend'] : null;
	$totals['network_roas'] = $totals['spend'] > 0 ? $totals['network_value'] / $totals['spend'] : null;
	$totals['ltv_roas'] = $totals['spend'] > 0 ? $totals['ltv'] / $totals['spend'] : null;

	return array(
		'since'             => $pSince,
		'until'             => $pUntil,
		'network'           => $network,
		'network_label'     => $netLabel,
		'click_window_days' => $clickDays,
		'window_source'     => isset( $netRow['window_source'] ) ? $netRow['window_source'] : null,
		'rows'              => $rows,
		'totals'            => $totals,
	);
}

function ads_roas_row( $pId, $pName, $pSpend, $pRev ) {
	$spend = (float)$pSpend['spend'];
	$revenue = (float)$pRev['revenue'];
	$ltv = isset( $pRev['ltv'] ) ? (float)$pRev['ltv'] : 0;
	$target = isset( $pSpend['target_roas'] ) && $pSpend['target_roas'] !== null && $pSpend['target_roas'] !== ''
		? (float)$pSpend['target_roas'] : null;
	$commerceRoas = $spend > 0 ? $revenue / $spend : null;
	$remoteRoas = $spend > 0 ? (float)$pSpend['network_value'] / $spend : null;
	return array(
		'campaign_id'   => $pId,
		'campaign_name' => $pName,
		'spend'         => $spend,
		'revenue'       => $revenue,
		'orders'        => (int)$pRev['orders'],
		'buyers'        => (int)$pRev['buyers'],
		'clicks'        => (int)$pSpend['clicks'],
		'impressions'   => (int)$pSpend['impressions'],
		'network_value' => (float)$pSpend['network_value'],
		'target_roas'   => $target,
		'ltv'           => $ltv,
		'commerce_roas' => $commerceRoas,
		'network_roas'  => $remoteRoas,
		'ltv_roas'      => $spend > 0 ? $ltv / $spend : null,
	);
}

function ads_roas_csv_headers( $pClickDays = null ) {
	return array(
		'campaign_id', 'campaign_name', 'window_spend',
		'commerce_revenue', 'commerce_orders', 'commerce_buyers', 'commerce_roas',
		'click_window_days', 'commerce_ltv', 'commerce_ltv_roas',
		'target_roas', 'network_value', 'network_roas', 'network_roas_is_partial',
	);
}

function ads_roas_csv_line( $pRow, $pClickDays = null ) {
	$roas = $pRow['commerce_roas'];
	$remote = $pRow['network_roas'];
	$ltvRoas = $pRow['ltv_roas'];
	return array(
		$pRow['campaign_id'],
		$pRow['campaign_name'],
		sprintf( '%.2f', $pRow['spend'] ),
		sprintf( '%.2f', $pRow['revenue'] ),
		(int)$pRow['orders'],
		(int)$pRow['buyers'],
		is_numeric( $roas ) ? sprintf( '%.3f', $roas ) : '',
		$pClickDays !== null ? (int)$pClickDays : '',
		sprintf( '%.2f', $pRow['ltv'] ),
		is_numeric( $ltvRoas ) ? sprintf( '%.3f', $ltvRoas ) : '',
		$pRow['target_roas'] !== null ? sprintf( '%.3f', $pRow['target_roas'] ) : '',
		sprintf( '%.2f', $pRow['network_value'] ),
		is_numeric( $remote ) ? sprintf( '%.3f', $remote ) : '',
		'click_window',
	);
}
