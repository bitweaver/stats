<?php
/**
 * Ad warehouse API credentials and how to obtain them.
 *
 * @package stats
 */

require_once( '../../kernel/includes/setup_inc.php' );
require_once( STATS_PKG_INCLUDE_PATH.'ads_api_lib.php' );
require_once( STATS_PKG_INCLUDE_PATH.'ads_warehouse_lib.php' );
require_once( STATS_PKG_INCLUDE_PATH.'ads_setup_lib.php' );

$gBitSystem->verifyPackage( 'stats' );
$gBitSystem->verifyPermission( 'p_stats_admin' );

$feedback = array();
$redirectUri = stats_ads_setup_redirect_uri( $_SERVER );

if( BitBase::getParameter( $_GET, 'code' ) ) {
	$state = BitBase::getParameter( $_GET, 'state', '' );
	if( $state === '' || $state !== $gBitUser->mTicket ) {
		$feedback['error'] = tra( 'OAuth state did not match. Try Sign in again.' );
	} else {
		$ex = stats_ads_microsoft_exchange_code( $_GET, $redirectUri );
		if( $ex === true ) {
			bit_redirect( STATS_PKG_URL.'admin/ad_setup.php?ms_ok=1' );
		} else {
			$feedback['error'] = $ex;
		}
	}
} elseif( BitBase::getParameter( $_GET, 'error' ) ) {
	$feedback['error'] = tra( 'Microsoft sign-in was cancelled or denied.' );
} elseif( BitBase::getParameter( $_GET, 'ms_ok' ) ) {
	$feedback['success'] = tra( 'Microsoft refresh token saved. It will not be shown again. Fill customer id and account id if still empty.' );
}

if( BitBase::getParameter( $_POST, 'save_ads_secrets' ) ) {
	$gBitUser->verifyTicket();
	$n = stats_ads_save_posted_secrets( $_POST );
	$feedback['success'] = tra( 'Saved '.$n.' value(s). Empty fields were left unchanged.' );
}

if( BitBase::getParameter( $_POST, 'save_campaign_aliases' ) ) {
	$gBitUser->verifyTicket();
	$aliasNetwork = BitBase::getParameter( $_POST, 'alias_network', 'google' );
	$nAlias = ads_save_campaign_aliases( $aliasNetwork, BitBase::getParameter( $_POST, 'campaign_aliases', '' ) );
	if( $nAlias === false ) {
		$feedback['error'] = tra( 'Apply the warehouse schema first (run the pull once).' );
	} else {
		$feedback['success'] = tra( 'Saved '.$nAlias.' campaign alias(es). Run the attribution backfill to apply them.' );
	}
}

$msAuth = stats_ads_microsoft_authorize_url( $gBitUser->mTicket, $redirectUri );

// Legacy tracking labels -> campaign id, one network at a time (google only today).
$aliasLines = array();
foreach( ads_campaign_alias_rows( 'google' ) as $alias => $id ) {
	$aliasLines[] = $alias.' = '.$id;
}
$unmatchedNames = array();
if( ads_warehouse_table_exists( 'stats_ad_user_attribution' ) ) {
	$unmatchedNames = $gBitSystem->mDb->getAll(
		"SELECT campaign_name, COUNT(*) AS users
		   FROM stats_ad_user_attribution
		  WHERE network_code = ? AND campaign_id IS NULL AND campaign_name IS NOT NULL
		  GROUP BY campaign_name ORDER BY COUNT(*) DESC LIMIT 25",
		array( 'google' )
	);
}

$catalog = stats_ads_setup_catalog();
foreach( $catalog as $code => $net ) {
	foreach( $net['keys'] as $key => $meta ) {
		$val = stats_ads_get_secret( $key );
		$catalog[$code]['keys'][$key]['set'] = ( $val !== null && $val !== '' );
		$catalog[$code]['keys'][$key]['value'] = ( $val !== null ) ? $val : '';
		$catalog[$code]['keys'][$key]['display'] = stats_ads_truncate( $val, 48 );
	}
}

$gBitSmarty->assign( 'feedback', $feedback );
$gBitSmarty->assign( 'adsCatalog', $catalog );
$gBitSmarty->assign( 'adsRedirectUri', $redirectUri );
$gBitSmarty->assign( 'adsMsAuthorize', $msAuth );
$gBitSmarty->assign( 'adsAliasText', implode( "\n", $aliasLines ) );
$gBitSmarty->assign( 'adsUnmatchedNames', $unmatchedNames );

$gBitSystem->display( 'bitpackage:stats/ad_setup.tpl', tra( 'Ad API setup' ), array( 'display_mode' => 'admin' ) );
