<?php

global $gBitSystem, $gUpgradeFrom, $gUpgradeTo;

$upgrades = array(

	'BWR1' => array(
		'BWR2' => array(
// de-tikify tables
array( 'DATADICT' => array(
	array( 'RENAMETABLE' => array(
		'tiki_referer_stats' => 'stats_referers',
		'tiki_pageviews'     => 'stats_pageviews',
	)),
	array( 'RENAMECOLUMN' => array(
		'stats_pageviews' => array(
			'`day`' => '`stats_day` I8 PRIMARY'
		),
	)),
)),
		)
	),

);

if( isset( $upgrades[$gUpgradeFrom][$gUpgradeTo] ) ) {
	$gBitSystem->registerUpgrade( STATS_PKG_NAME, $upgrades[$gUpgradeFrom][$gUpgradeTo] );
}
?>
