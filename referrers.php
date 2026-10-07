<?php
/**
 * $Header$
 *
 * $Id$
 * @package stats
 */

/**
 * required setup
 */
require_once( '../kernel/includes/setup_inc.php' );
include_once ( STATS_PKG_CLASS_PATH.'Statistics.php');
require_once( STATS_PKG_INCLUDE_PATH.'ads_roas_lib.php' );

$gBitSystem->verifyPackage( 'stats' );
$gBitSystem->verifyFeature( 'stats_referers' );
$gBitSystem->verifyPermission( 'p_stats_view_referer' );

$gStats = new Statistics();

if( empty( $_REQUEST['period'] ) || empty( $_REQUEST['timeframe'] ) ) {
	bit_redirect( STATS_PKG_URL.'users.php' );
}

// get rid of all referers in the database
if( isset( $_REQUEST["clear"] )) {
	$gStats->expungeReferers();
}
$referers = $gStats->getRefererList( $_REQUEST );
$totalRegistrations = 0;
$maxRegistrations = 0;

// The period's date range, for in-period revenue and the ROAS link.
$period = BitBase::getParameter( $_REQUEST, 'period', 'month' );
$timeframe = BitBase::getParameter( $_REQUEST, 'timeframe', '' );
$range = ads_roas_period_range( $period, $timeframe );
$since = $range ? $range['since'] : null;
$until = $range ? $range['until'] : null;

// Lifetime and in-period revenue per registered user in a few grouped
// queries, not one query per user.
$commerce = false;
$revMap = array();
if( $gBitSystem->isPackageActive( 'bitcommerce' ) ) {
	require_once( BITCOMMERCE_PKG_INCLUDE_PATH.'bitcommerce_start_inc.php' );
	$commerce = ads_roas_commerce_ready();
	if( $commerce ) {
		$ids = array();
		foreach( $referers as $rows ) {
			foreach( $rows as $row ) {
				$ids[] = (int)$row['user_id'];
			}
		}
		$revMap = ads_roas_user_revenue_map( $gBitSystem->mDb, $ids, $since ? $since : '1970-01-01', $until ? $until : date( 'Y-m-d' ) );
	}
}
$blankRevenue = array( 'total_revenue' => 0, 'total_orders' => 0, 'period_revenue' => 0, 'period_orders' => 0 );

$aggregateStats = array();
foreach( array_keys( $referers ) as $refSite ) {
	$refSiteCount = count( $referers[$refSite] );
	$totalRegistrations += $refSiteCount;
	if( $refSiteCount > $maxRegistrations ) {
		$maxRegistrations = $refSiteCount;
	}

	foreach( array_keys( $referers[$refSite] ) as $r ) {
		$url = parse_url( $referers[$refSite][$r]['referer_url'] );
		if( $commerce ) {
			$uid = (int)$referers[$refSite][$r]['user_id'];
			$revenue = isset( $revMap[$uid] ) ? $revMap[$uid] : $blankRevenue;
			$referers[$refSite][$r]['revenue'] = $revenue;
			$subVals = array( $refSite );
			$track = Statistics::trackingParamsFromRow( $referers[$refSite][$r] );
			if( Statistics::isPaidTracking( $track ) ) {
				array_push( $subVals, 'PPC' );
				// Keyed by campaign id (same resolver as the warehouse), titled by name.
				array_push( $subVals, Statistics::ppcCampaignNode( $referers[$refSite][$r], $track ) );
				$adgroup = Statistics::inferredAdGroup( $referers[$refSite][$r], $track );
				if( $adgroup !== '' ) {
					array_push( $subVals, $adgroup );
				}
				if( !empty( $track['ctm_term'] ) ) {
					array_push( $subVals, $track['ctm_term'] );
				}
			} else {
				$page = Statistics::landingPageKey( $referers[$refSite][$r] );
				$bucket = 'Organic';
				if( !empty( $url['query'] ) ) {
					$urlParams = array();
					parse_str( $url['query'], $urlParams );
					if( isset( $urlParams['pq'] ) ) {
						$bucket = 'Paid';
					}
				}
				if( $page !== '' ) {
					array_push( $subVals, $bucket, $page );
				} elseif( !empty( $url['path'] ) && $url['path'] != '/' ) {
					array_push( $subVals, $bucket, $url['path'] );
				} else {
					array_push( $subVals, 'Other' );
				}
			}
			computeStats( $aggregateStats, $subVals, $revenue, $referers[$refSite][$r] );
		}
	}
}

/**
 * Accumulate one user down a path of nodes. A node is a string (key and
 * title) or array( key, title, campaign_id ) from Statistics::ppcCampaignNode().
 */
function computeStats( &$pAggregateStats, &$subStats, $revenue, &$userHash ) {
	do {
		$item = array_shift( $subStats );
		if( is_array( $item ) ) {
			$key = $item['key'];
			$title = $item['title'];
		} else {
			$key = $title = $item;
		}
		if( !isset( $pAggregateStats[$key] ) ) {
			$pAggregateStats[$key] = array(
				'info' => array(
					'title' => $title, 'revenue' => 0, 'orders' => 0,
					'period_revenue' => 0, 'period_orders' => 0, 'users' => array(),
				),
				'values' => array(),
			);
		}
		if( is_array( $item ) && !empty( $item['campaign_id'] ) ) {
			$pAggregateStats[$key]['info']['campaign_id'] = $item['campaign_id'];
		}
		$pAggregateStats[$key]['info']['revenue'] += $revenue['total_revenue'];
		$pAggregateStats[$key]['info']['orders'] += $revenue['total_orders'];
		$pAggregateStats[$key]['info']['period_revenue'] += $revenue['period_revenue'];
		$pAggregateStats[$key]['info']['period_orders'] += $revenue['period_orders'];
		$pAggregateStats[$key]['info']['users'][] = $userHash;
		if( $subStats ) {
			computeStats( $pAggregateStats[$key]['values'], $subStats, $revenue, $userHash );
		}
	} while( !empty( $subStats ) );
}

$gBitThemes->loadCss(STATS_PKG_PATH.'css/stats.css', TRUE, 300, TRUE, FALSE, FALSE);
$gBitThemes->loadCss(CONFIG_PKG_PATH.'themes/bootstrap/bootstrap-table/bootstrap-table.css', TRUE, 300, TRUE, FALSE, FALSE);
$gBitThemes->loadJavascript(CONFIG_PKG_PATH.'themes/bootstrap/bootstrap-table/bootstrap-table.js', FALSE, 600, TRUE, FALSE);

$gBitSmarty->assignByRef( 'aggregateStats', $aggregateStats );
$gBitSmarty->assignByRef( 'referers', $referers );
$gBitSmarty->assign( 'totalRegistrations', $totalRegistrations );
$gBitSmarty->assign( 'maxRegistrations', $maxRegistrations );
$gBitSmarty->assign( 'listInfo', isset( $_REQUEST['listInfo'] ) ? $_REQUEST['listInfo'] : array() );
$roasPeriodUrl = null;
if( $range && $gBitUser->hasPermission( 'p_stats_admin' ) ) {
	$roasPeriodUrl = STATS_PKG_URL.'ad_roas.php?'.http_build_query( array( 'period' => $range['period'], 'timeframe' => $range['timeframe'] ) );
}
$gBitSmarty->assign( 'roasPeriodUrl', $roasPeriodUrl );
$gBitSmarty->assign( 'refSince', $since );
$gBitSmarty->assign( 'refUntil', $until );
$gBitSmarty->assign( 'refCommerce', $commerce );
$gBitSystem->display( 'bitpackage:stats/referrer_stats.tpl', tra( 'Referer Statistics' ), array( 'display_mode' => 'display' ));

