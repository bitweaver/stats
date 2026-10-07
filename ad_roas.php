<?php
/**
 * First-party ROAS: this install's books vs the ad network's reported value
 * per campaign, beside the bid target the network delivers toward.
 * Optional ad warehouse tables; no ad-network writes.
 *
 * @package stats
 */

require_once( '../kernel/includes/setup_inc.php' );
require_once( STATS_PKG_INCLUDE_PATH.'ads_roas_lib.php' );

$gBitSystem->verifyPackage( 'stats' );
$gBitSystem->verifyPermission( 'p_stats_admin' );

$range = ads_roas_range_from_request( $_REQUEST );
if( !empty( $range['error'] ) ) {
	$gBitSystem->fatalError( tra( $range['error'] ) );
}
$since = $range['since'];
$until = $range['until'];
$network = BitBase::getParameter( $_REQUEST, 'network', 'google' );
if( $network === '' ) {
	$network = 'google';
}
$campaignId = ads_roas_id_clean( BitBase::getParameter( $_REQUEST, 'campaign_id', '' ) );
$cohort = !empty( $_REQUEST['cohort'] );
// Optional campaign selection (comma list of ids); empty means every campaign.
$campaignFilter = array();
foreach( explode( ',', (string)BitBase::getParameter( $_REQUEST, 'campaigns', '' ) ) as $cid ) {
	$cid = ads_roas_id_clean( $cid );
	if( $cid !== '' ) {
		$campaignFilter[$cid] = $cid;
	}
}
$campaignFilter = array_values( $campaignFilter );

$feedback = array();
$report = null;
$networks = array();
$windowAsk = false;
$detail = null;
$svg = '';
$db = $gBitSystem->mDb;

if( !ads_roas_tables_ready( $db ) ) {
	$feedback['warning'] = tra( 'Ad warehouse tables are not installed on this database. Run the warehouse pull once.' );
} else {
	if( !empty( $_REQUEST['save_window'] ) ) {
		$gBitUser->verifyTicket();
		$days = (int)BitBase::getParameter( $_REQUEST, 'click_window_days', 0 );
		if( ads_roas_save_click_window( $db, $network, $days ) ) {
			$feedback['success'] = tra( 'Saved click-through conversion window.' );
		} else {
			$feedback['error'] = tra( 'Click window must be 1–90 days.' );
		}
	}
	if( !empty( $_REQUEST['save_assumptions'] ) ) {
		$gBitUser->verifyTicket();
		$ok = ads_roas_save_assumptions( $db, $_REQUEST );
		if( $ok === true ) {
			$feedback['success'] = tra( 'Saved assumptions.' );
		} else {
			$feedback['error'] = tra( $ok );
		}
	}
	$networks = ads_roas_networks( $db );
	if( !isset( $networks[$network] ) ) {
		$gBitSystem->fatalError( tra( 'Unknown ad network.' ) );
	}
	if( !ads_roas_commerce_ready() ) {
		$feedback['warning'] = tra( 'No revenue source is active. Spend is shown; revenue and ROAS need this install\'s orders.' );
	}
	$report = ads_roas_report( $db, $since, $until, array( 'network' => $network, 'campaign_ids' => $campaignFilter ) );
	$windowAsk = empty( $report['click_window_days'] );
	if( $campaignId !== '' ) {
		$detail = ads_roas_campaign_detail(
			$db, $network, $campaignId, $since, $until,
			$report['revenue_ready'] ? ads_roas_revenue_source( $db ) : null,
			$report['click_window_days']
		);
	}
	if( !empty( $report['series']['rows'] ) ) {
		$svg = ads_roas_svg_series( $report['series'], array(
			'break_even'     => $report['assumptions']['break_even_roas'],
			'immature_from'  => $report['immature_from'],
			'label_commerce' => tra( 'Commerce' ),
			'label_network'  => $report['network_label'],
			'label_target'   => tra( 'Target' ),
		) );
	}
}

if( !empty( $_REQUEST['download'] ) && is_array( $report ) ) {
	$filename = 'ad-roas-'.$network.'-'.$since.'-'.$until.'.csv';
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename='.$filename );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, ads_roas_csv_headers() );
	foreach( $report['rows'] as $row ) {
		fputcsv( $out, ads_roas_csv_line( $row ) );
	}
	$totals = $report['totals'];
	$totals['campaign_id'] = 'TOTAL';
	$totals['campaign_name'] = tra( 'Total' );
	$totals['flags'] = array();
	fputcsv( $out, ads_roas_csv_line( $totals ) );
	fclose( $out );
	exit;
}

if( $gBitSystem->isPackageActive( 'bitcommerce' ) ) {
	require_once( BITCOMMERCE_PKG_INCLUDE_PATH.'bitcommerce_start_inc.php' );
}

$gBitThemes->loadCss( STATS_PKG_PATH.'css/stats.css', TRUE, 300, TRUE, FALSE, FALSE );

$baseQuery = array( 'network' => $network, 'since' => $since, 'until' => $until );
if( $campaignFilter ) {
	$baseQuery['campaigns'] = implode( ',', $campaignFilter );
}
$referrersUrl = null;
if( !empty( $range['period'] ) && !empty( $range['timeframe'] ) ) {
	$referrersUrl = STATS_PKG_URL.'referrers.php?'.http_build_query( array( 'period' => $range['period'], 'timeframe' => $range['timeframe'] ) );
}
// Pager: the previous / next period, or for a custom range the same number of days.
$pageQuery = array( 'network' => $network );
if( $campaignFilter ) {
	$pageQuery['campaigns'] = implode( ',', $campaignFilter );
}
$sinceTs = strtotime( $since.' 12:00:00' );
$untilTs = strtotime( $until.' 12:00:00' );
if( !empty( $range['period'] ) && !empty( $range['timeframe'] ) ) {
	$prevQ = array( 'period' => $range['period'], 'timeframe' => ads_roas_timeframe_for( $range['period'], date( 'Y-m-d', $sinceTs - 86400 ) ) );
	$nextSince = date( 'Y-m-d', $untilTs + 86400 );
	$nextQ = array( 'period' => $range['period'], 'timeframe' => ads_roas_timeframe_for( $range['period'], $nextSince ) );
} else {
	$len = max( 1, (int)$range['days'] );
	$prevQ = array( 'since' => date( 'Y-m-d', $sinceTs - $len * 86400 ), 'until' => date( 'Y-m-d', $sinceTs - 86400 ) );
	$nextSince = date( 'Y-m-d', $untilTs + 86400 );
	$nextQ = array( 'since' => $nextSince, 'until' => date( 'Y-m-d', $untilTs + $len * 86400 ) );
}
$prevUrl = STATS_PKG_URL.'ad_roas.php?'.http_build_query( $prevQ + $pageQuery );
$nextUrl = ( $nextSince <= date( 'Y-m-d' ) ) ? STATS_PKG_URL.'ad_roas.php?'.http_build_query( $nextQ + $pageQuery ) : null;

$gBitSmarty->assign( 'feedback', $feedback );
$gBitSmarty->assign( 'roasRange', $range );
$gBitSmarty->assign( 'roasSince', $since );
$gBitSmarty->assign( 'roasUntil', $until );
$gBitSmarty->assign( 'roasNetwork', $network );
$gBitSmarty->assign( 'roasNetworks', $networks );
$gBitSmarty->assign( 'roasPresets', ads_roas_presets() );
$gBitSmarty->assign( 'roasPeriods', ads_roas_periods() );
$gBitSmarty->assign( 'roasReport', $report );
$gBitSmarty->assign( 'roasWindowAsk', $windowAsk );
$gBitSmarty->assign( 'roasDetail', $detail );
$gBitSmarty->assign( 'roasSvg', $svg );
$gBitSmarty->assign( 'roasCampaignId', $campaignId );
$gBitSmarty->assign( 'roasCohort', $cohort );
$gBitSmarty->assign( 'roasBaseQuery', http_build_query( $baseQuery ) );
$gBitSmarty->assign( 'roasUnfilteredQuery', http_build_query( array( 'network' => $network, 'since' => $since, 'until' => $until ) ) );
$gBitSmarty->assign( 'roasCampaignFilter', $campaignFilter );
$gBitSmarty->assign( 'roasCampaignFilterStr', implode( ',', $campaignFilter ) );
$gBitSmarty->assign( 'roasPeriodChoices', array( 'week' => 'Weekly', 'month' => 'Monthly', 'quarter' => 'Quarterly', 'year' => 'Yearly', 'custom' => 'Custom' ) );
$gBitSmarty->assign( 'roasReferrersUrl', $referrersUrl );
$gBitSmarty->assign( 'roasPrevUrl', $prevUrl );
$gBitSmarty->assign( 'roasNextUrl', $nextUrl );

$gBitSystem->display( 'bitpackage:stats/ad_roas.tpl', tra( 'Ad ROAS' ), array( 'display_mode' => 'display' ) );
