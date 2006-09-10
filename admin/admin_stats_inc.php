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
}

?>
