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

$aggregateStats = array();
foreach( array_keys( $referers ) as $refSite ) {
	$refSiteCount = count( $referers[$refSite] );
	$totalRegistrations += $refSiteCount;
	if( $refSiteCount > $maxRegistrations ) {
		$maxRegistrations = $refSiteCount;
	}

	foreach( array_keys( $referers[$refSite] ) as $r ) {
		$url = parse_url( $referers[$refSite][$r]['referer_url'] );
		$revenue = array();
		if( $gBitSystem->isPackageActive( 'bitcommerce' ) ) {
			require_once( BITCOMMERCE_PKG_INCLUDE_PATH.'bitcommerce_start_inc.php' );
			require_once( BITCOMMERCE_PKG_CLASS_PATH.'CommerceStatistics.php' );
			$revenue = $gCommerceStatistics->getCustomerRevenue( array( 'customers_id' => $referers[$refSite][$r]['user_id'] ) );
			$referers[$refSite][$r]['revenue'] = $revenue;
			$subVals = array( $refSite );
			if( !empty( $url['query'] ) ) {
				$urlParams = array();
				parse_str( $url['query'], $urlParams );
				if( !empty( $urlParams['adurl'] ) ) {
					$adUrl = parse_url( $urlParams['adurl'] );
					if( !empty( $adUrl['query'] ) ) {
						array_push( $subVals, 'PPC' );
						$adParams = array();
						parse_str( $adUrl['query'], $adParams );
						foreach( array( 'ctm_campaign', 'ctm_adgroup', 'ctm_term' ) as $subKey ) {
							if( isset( $adParams[$subKey] ) ) {
								$subKeyVal = !empty( $adParams[$subKey] ) ? $adParams[$subKey] : 'unknown' ;
								array_push( $subVals, $subKeyVal );
							}
						}
					} else {
						array_push( $subVals, 'Paid', $adUrl['path'] );
					}
				} else {
					// bing paid query
					foreach( array( 'pq' => 'Paid', 'q' => 'Organic', 'p' => 'Organic', 'unknown' => 'Unknown' ) as $key=>$title ) {
						if( $key == 'unknown' || isset( $urlParams[$key] ) ) {
							array_push( $subVals, $title, BitBase::getParameter( $urlParams, $key, 'unknown' ) );
							break;
						}
					}
				}
			} else {
if( !empty( $url['path'] ) && $url['path'] != '/' ) {
	array_push( $subVals, $url['path'] );
}

			}
			computeStats( $aggregateStats, $subVals, $revenue, $referers[$refSite][$r] );
		}
	}
}

function computeStats( &$pAggregateStats, &$subStats, $revenue, &$userHash ) {
				do {
					$subStatKey = array_shift( $subStats );
					@$pAggregateStats[$subStatKey]['info']['title'] = $subStatKey;
					@$pAggregateStats[$subStatKey]['info']['revenue'] += $revenue['total_revenue'];
					@$pAggregateStats[$subStatKey]['info']['orders'] += $revenue['total_orders'];
					@$pAggregateStats[$subStatKey]['info']['users'][] = $userHash;
					if( empty( $pAggregateStats[$subStatKey]['values'] ) ) {
						$pAggregateStats[$subStatKey]['values'] = array();
					}
					if( $subStats ) {
						computeStats( $pAggregateStats[$subStatKey]['values'], $subStats, $revenue, $userHash );
					}
				} while( !empty( $subStats ) );
//global $aggregateStats; eb( $subStatKey, $pTitle, $subStats, $revenue, $userHash, $aggregateStats );
/*
			@$pAggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['values'][$adParams['ctm_adgroup']]['values'][$adParams['ctm_term']]['info']['title'] = $adParams['ctm_term'];
			@$pAggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['values'][$adParams['ctm_adgroup']]['values'][$adParams['ctm_term']]['info']['revenue'] += $revenue['total_revenue'];
			@$pAggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['values'][$adParams['ctm_adgroup']]['values'][$adParams['ctm_term']]['info']['orders'] += $revenue['total_orders'];
			@$pAggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['values'][$adParams['ctm_adgroup']]['values'][$adParams['ctm_term']]['info']['users'][] = $userHash;

			@$pAggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['values'][$adParams['ctm_adgroup']]['info']['title'] = $adParams['ctm_adgroup'];
			@$pAggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['values'][$adParams['ctm_adgroup']]['info']['revenue'] += $revenue['total_revenue'];
			@$pAggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['values'][$adParams['ctm_adgroup']]['info']['orders'] += $revenue['total_orders'];
			@$pAggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['values'][$adParams['ctm_adgroup']]['info']['users'][] = $userHash;

			@$pAggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['info']['title'] = $adParams['ctm_campaign'];
			@$pAggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['info']['revenue'] += $revenue['total_revenue'];
			@$pAggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['info']['orders'] += $revenue['total_orders'];
			@$pAggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['info']['users'][] = $userHash;

	} else {
		foreach( $adParams as $key => $value ) {
			computeStats( $pAggregateStats, $k, $key, $value, $revenue, $userHash );
		}
	}
*/
}
/*
function computeStats( &$pAggregateStats, $k, $key, $value, $revenue, &$userHash, $subKey = NULL ) {
	@$pAggregateStats[$k]['values'][$key]['info']['title'] = $key;
	@$pAggregateStats[$k]['values'][$key]['info']['revenue'] += $revenue['total_revenue'];
	@$pAggregateStats[$k]['values'][$key]['info']['orders'] += $revenue['total_orders'];
	@$pAggregateStats[$k]['values'][$key]['info']['users'][] = $userHash;

	if( $subKey ) {
		@$pAggregateStats[$k]['values'][$key]['values'][$subKey]['info']['title'] = $subKey;
		@$pAggregateStats[$k]['values'][$key]['values'][$subKey]['info']['revenue'] += $revenue['total_revenue'];
		@$pAggregateStats[$k]['values'][$key]['values'][$subKey]['info']['orders'] += $revenue['total_orders'];
		@$pAggregateStats[$k]['values'][$key]['values'][$subKey]['info']['users'][] = $userHash;
	}

}
*/

$gBitThemes->loadCss( STATS_PKG_PATH.'css/stats.css');
$gBitThemes->loadCss( CONFIG_PKG_PATH.'themes/bootstrap/bootstrap-table/bootstrap-table.css');
$gBitThemes->loadJavascript( CONFIG_PKG_PATH.'themes/bootstrap/bootstrap-table/bootstrap-table.js');

$gBitSmarty->assignByRef( 'aggregateStats', $aggregateStats );
$gBitSmarty->assignByRef( 'referers', $referers );
$gBitSmarty->assign( 'totalRegistrations', $totalRegistrations );
$gBitSmarty->assign( 'maxRegistrations', $maxRegistrations );
$gBitSmarty->assign( 'listInfo', $_REQUEST['listInfo'] );
$gBitSystem->display( 'bitpackage:stats/referrer_stats.tpl', tra( 'Referer Statistics' ), array( 'display_mode' => 'display' ));

