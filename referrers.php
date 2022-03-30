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
foreach( array_keys( $referers ) as $k ) {
	$kCount = count( $referers[$k] );
	$totalRegistrations += $kCount;
	if( $kCount > $maxRegistrations ) {
		$maxRegistrations = $kCount;
	}

	foreach( array_keys( $referers[$k] ) as $r ) {
		$url = parse_url( $referers[$k][$r]['referer_url'] );
		$revenue = array();
		if( $gBitSystem->isPackageActive( 'bitcommerce' ) ) {
			require_once( BITCOMMERCE_PKG_INCLUDE_PATH.'bitcommerce_start_inc.php' );
			require_once( BITCOMMERCE_PKG_CLASS_PATH.'CommerceStatistics.php' );
			$revenue = $gCommerceStatistics->getCustomerRevenue( array( 'customers_id' => $referers[$k][$r]['user_id'] ) );
			$referers[$k][$r]['revenue'] = $revenue;
			if( !empty( $url['query'] ) ) {
				$urlParams = array();
				parse_str( $url['query'], $urlParams );
				if( !empty( $urlParams['adurl'] ) ) {
					$adUrl = parse_url( $urlParams['adurl'] );
					if( !empty( $adUrl['query'] ) ) {
						$adParams = array();
						computeStatsTree( $aggregateStats, $k, $adUrl['query'], $revenue, $referers[$k][$r] );
					} else {
						$key = 'Paid';
						$value = $adUrl['path'];
						computeStats( $aggregateStats, $k, $key, $value, $revenue, $referers[$k][$r] );
					}
				} else {
					// bing paid query
					foreach( array( 'pq' => 'Paid', 'q' => 'Organic', 'p' => 'Organic', 'unknown' => 'Unknown' ) as $key=>$title ) {
						if( $key == 'unknown' || isset( $urlParams[$key] ) ) {
							$value = BitBase::getParameter( $urlParams, $key, 'unknown' );
							computeStats( $aggregateStats, $k, $title, $value, $revenue, $referers[$k][$r], BitBase::getParameter( $urlParams, $key, $key ) );
							break;
						}
					}
				}
			}
			@$aggregateStats[$k]['info']['revenue'] += $revenue['total_revenue'];
			@$aggregateStats[$k]['info']['orders'] += $revenue['total_orders'];
		}
	}
}

function computeStatsTree( &$aggregateStats, $k, $queryUrl, $revenue, &$userHash ) {
	parse_str( $queryUrl, $adParams );
	if( isset( $adParams['ctm_campaign'] ) && isset( $adParams['ctm_adgroup'] ) && isset( $adParams['ctm_term'] ) ) {
		$key = 'PPC';
			@$aggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['values'][$adParams['ctm_adgroup']]['values'][$adParams['ctm_term']]['info']['title'] = $adParams['ctm_term'];
			@$aggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['values'][$adParams['ctm_adgroup']]['values'][$adParams['ctm_term']]['info']['revenue'] += $revenue['total_revenue'];
			@$aggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['values'][$adParams['ctm_adgroup']]['values'][$adParams['ctm_term']]['info']['orders'] += $revenue['total_orders'];
			@$aggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['values'][$adParams['ctm_adgroup']]['values'][$adParams['ctm_term']]['info']['users'][] = $userHash;

			@$aggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['values'][$adParams['ctm_adgroup']]['info']['title'] = $adParams['ctm_adgroup'];
			@$aggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['values'][$adParams['ctm_adgroup']]['info']['revenue'] += $revenue['total_revenue'];
			@$aggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['values'][$adParams['ctm_adgroup']]['info']['orders'] += $revenue['total_orders'];
			@$aggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['values'][$adParams['ctm_adgroup']]['info']['users'][] = $userHash;

			@$aggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['info']['title'] = $adParams['ctm_campaign'];
			@$aggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['info']['revenue'] += $revenue['total_revenue'];
			@$aggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['info']['orders'] += $revenue['total_orders'];
			@$aggregateStats[$k]['values'][$key]['values'][$adParams['ctm_campaign']]['info']['users'][] = $userHash;

			@$aggregateStats[$k]['values'][$key]['info']['title'] = $key;
			@$aggregateStats[$k]['values'][$key]['info']['revenue'] += $revenue['total_revenue'];
			@$aggregateStats[$k]['values'][$key]['info']['orders'] += $revenue['total_orders'];
			@$aggregateStats[$k]['values'][$key]['info']['users'][] = $userHash;
	} else {
		foreach( $adParams as $key => $value ) {
			computeStats( $aggregateStats, $k, $key, $value, $revenue, $userHash );
		}
	}
}

function computeStats( &$aggregateStats, $k, $key, $value, $revenue, &$userHash, $subKey = NULL ) {
	@$aggregateStats[$k]['values'][$key]['info']['title'] = $key;
	@$aggregateStats[$k]['values'][$key]['info']['revenue'] += $revenue['total_revenue'];
	@$aggregateStats[$k]['values'][$key]['info']['orders'] += $revenue['total_orders'];
	@$aggregateStats[$k]['values'][$key]['info']['users'][] = $userHash;

	if( $subKey ) {
		@$aggregateStats[$k]['values'][$key]['values'][$subKey]['info']['title'] = $subKey;
		@$aggregateStats[$k]['values'][$key]['values'][$subKey]['info']['revenue'] += $revenue['total_revenue'];
		@$aggregateStats[$k]['values'][$key]['values'][$subKey]['info']['orders'] += $revenue['total_orders'];
		@$aggregateStats[$k]['values'][$key]['values'][$subKey]['info']['users'][] = $userHash;
	}

}

$gBitThemes->loadCss( STATS_PKG_PATH.'css/stats.css');
$gBitThemes->loadCss( CONFIG_PKG_PATH.'themes/bootstrap/bootstrap-table/bootstrap-table.css');
$gBitThemes->loadJavascript( CONFIG_PKG_PATH.'themes/bootstrap/bootstrap-table/bootstrap-table.js');

$gBitSmarty->assignByRef( 'aggregateStats', $aggregateStats );
$gBitSmarty->assignByRef( 'referers', $referers );
$gBitSmarty->assign( 'totalRegistrations', $totalRegistrations );
$gBitSmarty->assign( 'maxRegistrations', $maxRegistrations );
$gBitSmarty->assign( 'listInfo', $_REQUEST['listInfo'] );
$gBitSystem->display( 'bitpackage:stats/referrer_stats.tpl', tra( 'Referer Statistics' ), array( 'display_mode' => 'display' ));

