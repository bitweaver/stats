<?php
global $gBitSystem;

$registerHash = array(
	'package_name' => 'stats',
	'package_path' => dirname( __FILE__ ).'/',
);
$gBitSystem->registerPackage( $registerHash );

if( $gBitSystem->isPackageActive( STATS_PKG_NAME ) ) {
	if( $gBitUser->hasPermission( 'bit_p_view_site_stats' ) || $gBitUser->hasPermission( 'bit_p_view_ref_stats' ) ) {
		$gBitSystem->registerAppMenu( STATS_PKG_NAME, ucfirst( STATS_PKG_DIR ), STATS_PKG_URL.'index.php', 'bitpackage:stats/menu_stats.tpl', 'stats');
	}
	global $statslib;
	require_once( STATS_PKG_PATH.'stats_lib.php' );
	// **********  STATS  ************
	if ($gBitSystem->isFeatureActive( 'referer_stats' ) ) {
		// Referer tracking
		if (isset($_SERVER['HTTP_REFERER'])) {
			$pref = parse_url($_SERVER['HTTP_REFERER']);
			if( !empty( $pref["host"] ) && !strstr( $_SERVER["HTTP_HOST"], $pref["host"] ) ) {
				$statslib->register_referer($pref["host"]);
			}
		}
	}
	if( $gBitSystem->isFeatureActive( "count_admin_pvs" ) || !$gBitUser->isAdmin() ) {
		if ( isset($_SERVER["REQUEST_URI"]) && !strstr($_SERVER["REQUEST_URI"], 'chat')) {
			$statslib->add_pageview();
		}
	}
}

?>
