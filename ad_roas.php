<?php
/**
 * First-party ROAS: Commerce (Bitcommerce) vs network ROAS for this install.
 * Optional ad warehouse tables; no ad-network conversion upload.
 *
 * @package stats
 */

require_once( '../kernel/includes/setup_inc.php' );
require_once( STATS_PKG_INCLUDE_PATH.'ads_roas_lib.php' );

$gBitSystem->verifyPackage( 'stats' );
$gBitSystem->verifyPermission( 'p_stats_admin' );

$since = BitBase::getParameter( $_REQUEST, 'since', date( 'Y-m-d', strtotime( '-180 days' ) ) );
$until = BitBase::getParameter( $_REQUEST, 'until', date( 'Y-m-d' ) );
$network = BitBase::getParameter( $_REQUEST, 'network', 'google' );

if( !ads_roas_date_ok( $since ) || !ads_roas_date_ok( $until ) ) {
	$gBitSystem->fatalError( tra( 'Dates must be Y-m-d.' ) );
}

$feedback = array();
$report = null;
$networks = array();
$windowAsk = false;

if( !ads_roas_tables_ready( $gBitSystem->mDb ) ) {
	$feedback['warning'] = tra( 'Ad warehouse tables are not installed on this database.' );
} else {
	if( !empty( $_REQUEST['save_window'] ) ) {
		$gBitUser->verifyTicket();
		$days = (int)BitBase::getParameter( $_REQUEST, 'click_window_days', 0 );
		if( ads_roas_save_click_window( $gBitSystem->mDb, $network, $days ) ) {
			$feedback['success'] = tra( 'Saved click-through conversion window.' );
		} else {
			$feedback['error'] = tra( 'Click window must be 1–90 days.' );
		}
	}
	if( !ads_roas_commerce_ready() ) {
		$feedback['warning'] = tra( 'Bitcommerce is not active. Spend is shown; revenue and ROAS need this install\'s orders.' );
	}
	$networks = ads_roas_networks( $gBitSystem->mDb );
	if( $network !== '' && !isset( $networks[$network] ) ) {
		$gBitSystem->fatalError( tra( 'Unknown ad network.' ) );
	}
	$report = ads_roas_report( $gBitSystem->mDb, $since, $until, array(
		'network' => $network,
	) );
	$windowAsk = empty( $report['click_window_days'] );
}

if( !empty( $_REQUEST['download'] ) && is_array( $report ) ) {
	$filename = 'ad-roas-'.$network.'-'.$since.'-'.$until.'.csv';
	header( 'Content-Type: text/csv' );
	header( 'Content-Disposition: attachment; filename='.$filename );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	$out = fopen( 'php://output', 'w' );
	$clickDays = $report['click_window_days'];
	fputcsv( $out, ads_roas_csv_headers( $clickDays ) );
	foreach( $report['rows'] as $row ) {
		fputcsv( $out, ads_roas_csv_line( $row, $clickDays ) );
	}
	exit;
}

if( $gBitSystem->isPackageActive( 'bitcommerce' ) ) {
	require_once( BITCOMMERCE_PKG_INCLUDE_PATH.'bitcommerce_start_inc.php' );
}

$gBitSmarty->assign( 'feedback', $feedback );
$gBitSmarty->assign( 'roasSince', $since );
$gBitSmarty->assign( 'roasUntil', $until );
$gBitSmarty->assign( 'roasNetwork', $network );
$gBitSmarty->assign( 'roasNetworks', $networks );
$gBitSmarty->assign( 'roasReport', $report );
$gBitSmarty->assign( 'roasWindowAsk', $windowAsk );

$gBitSystem->display( 'bitpackage:stats/ad_roas.tpl', tra( 'Ad ROAS' ), array( 'display_mode' => 'display' ) );
