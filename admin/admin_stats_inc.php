<?php
$formFeaturesBit = array( 
	'stats_referers' => array(
		'label' => 'Referer Statistics',
		'type' => "checkbox",
		'note' => 'Records statistics including HTTP_REFERRER',
	),
	'google_tagmanager_id' => array(
		'label' => "Google TagManager Container ID for GA4",
		'type' => "text",
		'note' => "TagManager Container ID, which should be conected to your GA4 measurement ID; e.g. GTM-ABCD1234 See from https://tagmanager.google.com",
	),
	'google_analytics_ua' => array(
		'label' => "Google Analytics UA (DISCONTINUED)",
		'type' => "text",
		'note' => "UA from anayltics.google.com; discontinued June 30, 2023",
	),
	'microsoft_analytics_ti' => array(
		'label' => "Microsoft Analytics TI",
		'type' => "text",
		'note' => "TI from ads.microsoft.com conversion javascript",
	),
);

$gBitSmarty->assign( 'formFeaturesBit', $formFeaturesBit );

if( !empty( $_POST['change_prefs'] ) ) {
	foreach ( array_keys( $formFeaturesBit ) as $feature) {
		if( $formFeaturesBit[$feature]['type'] == 'text' ) {
			simple_set_value( $feature, STATS_PKG_NAME );
		} else {
			simple_set_toggle( $feature, STATS_PKG_NAME );
		}
	}
}

