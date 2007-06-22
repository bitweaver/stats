<?php
global $gBitSystem, $gBitUser ;

$registerHash = array(
	'package_name' => 'stats',
	'package_path' => dirname( __FILE__ ).'/',
);
$gBitSystem->registerPackage( $registerHash );

if( $gBitSystem->isPackageActive( 'stats' )) {
	if( $gBitUser->hasPermission( 'p_stats_view' ) || $gBitUser->hasPermission( 'p_stats_view_referer' ) ) {
		$menuHash = array(
			'package_name'  => STATS_PKG_NAME,
			'index_url'     => STATS_PKG_URL.'index.php',
			'menu_template' => 'bitpackage:stats/menu_stats.tpl',
		);
		$gBitSystem->registerAppMenu( $menuHash );
	}

	require_once( STATS_PKG_PATH.'Statistics.php' );
	$stats = new Statistics();
	$stats->addPageview();

	// store referer stats if desired
	if( $gBitSystem->isFeatureActive( 'stats_referers' )) {
		$stats->storeReferer();
	}
}
?>
