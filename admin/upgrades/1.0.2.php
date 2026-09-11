<?php
/**
 * First-touch landing URL storage for registration attribution.
 *
 * Tracking query keys (ctm_*, utm_*, gclid, …) live on the landing URI, not on
 * HTTP_REFERER. This upgrade matches tables already present on some installs.
 */
global $gBitInstaller;

$infoHash = array(
	'package'      => STATS_PKG_NAME,
	'version'      => str_replace( '.php', '', basename( __FILE__ )),
	'description'  => "Store first-touch landing URL and query for registration attribution.",
	'post_upgrade' => NULL,
);
$gBitInstaller->registerPackageUpgrade( $infoHash, array(
	array( 'DATADICT' => array(
		array( 'CREATESEQUENCE' => array(
			'stats_landing_url_id_seq',
		)),
		array( 'CREATE' => array(
			'stats_landing_urls' => "
				landing_url_id I4 PRIMARY,
				landing_url C(4096) NOTNULL,
				landing_query C(4096)
			",
		)),
		array( 'ALTER' => array(
			'stats_referer_users_map' => array(
				'landing_url_id' => array( '`landing_url_id`', 'I4' ),
			),
		)),
		array( 'CREATEINDEX' => array(
			'stats_referer_map_landing_idx' => array( 'stats_referer_users_map', 'landing_url_id', array() ),
		)),
	)),
));
