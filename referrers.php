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
require_once( '../kernel/setup_inc.php' );
include_once ( STATS_PKG_PATH.'Statistics.php');

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
			require_once( BITCOMMERCE_PKG_PATH.'includes/bitcommerce_start_inc.php' );
			require_once( BITCOMMERCE_PKG_PATH.'classes/CommerceStatistics.php' );
			$revenue = $gCommerceStatistics->getCustomerRevenue( array( 'customers_id' => $referers[$k][$r]['user_id'] ) );
			$referers[$k][$r]['revenue'] = $revenue;
		}
		if( !empty( $url['query'] ) ) {
			$urlParams = array();
			parse_str( $url['query'], $urlParams );
			if( !empty( $urlParams['adurl'] ) ) {
				$adUrl = parse_url( $urlParams['adurl'] );
				if( !empty( $adUrl['query'] ) ) {
					$adParams = array();
					parse_str( $adUrl['query'], $adParams );
					foreach( $adParams as $key => $value ) {
						computeStats( $aggregateStats, $k, $key, $value, $revenue, $referers[$k][$r] );
					}
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
						computeStats( $aggregateStats, $k, $title, $value, $revenue, $referers[$k][$r] );
						break;
					}
				}
			}
		}
		if( !empty( $revenue['total_orders'] ) ) {
			@$aggregateStats[$k]['revenue'] += $revenue['total_revenue'];
			@$aggregateStats[$k]['orders'] += $revenue['total_orders'];
		}
	}
}

function computeStats( &$aggregateStats, $k, $key, $value, $revenue, $userHash ) {
	if( !empty( $revenue['total_orders'] ) ) {
		@$aggregateStats[$k]['values'][$key]['values'][$value]['revenue'] += $revenue['total_revenue'];
		@$aggregateStats[$k]['values'][$key]['values'][$value]['orders'] += $revenue['total_orders'];
		@$aggregateStats[$k]['values'][$key]['revenue'] += $revenue['total_revenue'];
		@$aggregateStats[$k]['values'][$key]['orders'] += $revenue['total_orders'];
	}
	$aggregateStats[$k]['values'][$key]['values'][$value]['users'][] = $userHash;
	@$aggregateStats[$k]['values'][$key]['registrations']++;
}

$gBitThemes->loadCss( CONFIG_PKG_PATH.'themes/bootstrap/bootstrap-table/bootstrap-table.css');
$gBitThemes->loadJavascript( CONFIG_PKG_PATH.'themes/bootstrap/bootstrap-table/bootstrap-table.js');

$gBitSmarty->assign_by_ref( 'aggregateStats', $aggregateStats );
$gBitSmarty->assign_by_ref( 'referers', $referers );
$gBitSmarty->assign( 'totalRegistrations', $totalRegistrations );
$gBitSmarty->assign( 'maxRegistrations', $maxRegistrations );
$gBitSmarty->assign( 'listInfo', $_REQUEST['listInfo'] );
$gBitSystem->display( 'bitpackage:stats/referrer_stats.tpl', tra( 'Referer Statistics' ), array( 'display_mode' => 'display' ));
?>
