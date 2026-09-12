<?php
/**
 * Ad warehouse API credentials and how to obtain them.
 *
 * @package stats
 */

require_once( '../kernel/includes/setup_inc.php' );
require_once( STATS_PKG_INCLUDE_PATH.'ad_setup_inc.php' );

$gBitSystem->verifyPackage( 'stats' );
$gBitSystem->verifyPermission( 'p_stats_admin' );

$feedback = array();
$redirectUri = stats_ads_setup_redirect_uri();

if( !empty( $_GET['code'] ) ) {
	$state = BitBase::getParameter( $_GET, 'state', '' );
	if( $state === '' || $state !== $gBitUser->mTicket ) {
		$feedback['error'] = tra( 'OAuth state did not match. Try Sign in again.' );
	} else {
		$ex = stats_ads_microsoft_exchange_code( $_GET['code'] );
		if( $ex === true ) {
			bit_redirect( STATS_PKG_URL.'ad_setup.php?ms_ok=1' );
		} else {
			$feedback['error'] = $ex;
		}
	}
} elseif( !empty( $_GET['error'] ) ) {
	$feedback['error'] = tra( 'Microsoft sign-in was cancelled or denied.' );
} elseif( !empty( $_GET['ms_ok'] ) ) {
	$feedback['success'] = tra( 'Microsoft refresh token saved. It will not be shown again. Fill customer id and account id if still empty.' );
}

if( !empty( $_POST['save_ads_secrets'] ) ) {
	$gBitUser->verifyTicket();
	$n = stats_ads_save_posted_secrets();
	$feedback['success'] = tra( 'Saved '.$n.' value(s). Empty fields were left unchanged.' );
}

$msAuth = stats_ads_microsoft_authorize_url( $gBitUser->mTicket );

$gBitSmarty->assign( 'feedback', $feedback );
$gBitSmarty->assign( 'adsCatalog', stats_ads_setup_catalog() );
$gBitSmarty->assign( 'adsStatus', stats_ads_status_rows() );
$gBitSmarty->assign( 'adsRedirectUri', $redirectUri );
$gBitSmarty->assign( 'adsMsAuthorize', $msAuth );

$gBitSystem->display( 'bitpackage:stats/ad_setup.tpl', tra( 'Ad API setup' ), array( 'display_mode' => 'admin' ) );
