<?php
$formFeaturesBit['stats_referers'] = array(
	'label' => 'Referer Statistics',
	'note' => 'Records statistics including HTTP_REFERRER',
);
$gBitSmarty->assign( 'formFeaturesBit', $formFeaturesBit );

if( !empty( $_REQUEST['change_prefs'] ) ) {
	foreach( $formFeaturesBit as $item => $info ) {
		simple_set_toggle( $item, STATS_PKG_NAME );
	}
	$gBitSystem->storeConfig( 'analytics_google_ua', BitBase::getParameter( $_REQUEST, 'analytics_google_ua' ) );
	$gBitSystem->storeConfig( 'analytics_microsoft_ti', BitBase::getParameter( $_REQUEST, 'analytics_microsoft_ti' ) );
}

