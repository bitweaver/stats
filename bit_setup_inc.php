<?php
global $gBitSystem, $gBitUser ;

$registerHash = array(
	'package_name' => 'stats',
	'package_path' => dirname( __FILE__ ).'/',
);
$gBitSystem->registerPackage( $registerHash );

if( $gBitSystem->isPackageActive( STATS_PKG_NAME ) ) {
	if( $gBitUser->hasPermission( 'p_stats_view' ) || $gBitUser->hasPermission( 'p_stats_view_referer' ) ) {
		$menuHash = array(
			'package_name'  => STATS_PKG_NAME,
			'index_url'     => STATS_PKG_URL.'index.php',
			'menu_template' => 'bitpackage:stats/menu_stats.tpl',
		);
		$gBitSystem->registerAppMenu( $menuHash );
	}
	global $statslib;
	require_once( STATS_PKG_PATH.'stats_lib.php' );
	// **********  STATS  ************
	if ($gBitSystem->isFeatureActive( 'stats_referers' ) ) {
		// Referer tracking
		if (isset($_SERVER['HTTP_REFERER'])) {
			$pref = parse_url($_SERVER['HTTP_REFERER']);
			if( !empty( $pref["host"] ) && !strstr( $_SERVER["HTTP_HOST"], $pref["host"] ) ) {
				$statslib->register_referer($pref["host"]);
			}
		}
	}
	if( $gBitSystem->isFeatureActive( 'users_count_admin_pageviews' ) && !$gBitUser->isAdmin() ) {
		if ( isset($_SERVER["REQUEST_URI"]) && !strstr($_SERVER["REQUEST_URI"], 'chat')) {
			$statslib->add_pageview();
		}
	}
}

?>
