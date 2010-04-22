<?php
/**
 * @version $Header: /cvsroot/bitweaver/_bit_stats/admin/upgrades/1.0.1.php,v 1.1 2010/04/22 21:24:49 spiderr Exp $
 */
global $gBitInstaller;

$infoHash = array(
	'package'      => STATS_PKG_NAME,
	'version'      => str_replace( '.php', '', basename( __FILE__ )),
	'description'  => "Add referer URL tracking for user registrations.",
	'post_upgrade' => NULL,
);
$gBitInstaller->registerPackageUpgrade( $infoHash, array(
	array( 'DATADICT' => array(
		array( 'CREATE' => array(
			'stats_referer_urls' => "
				referer_url_id I4 PRIMARY,
				referer_url X NOTNULL
			",	
		)),
		array( 'CREATEINDEX' => array(
			'stats_referer_url_idx'       => array( 'stats_referer_urls', 'referer_url', array() ),
		)),
		array( 'CREATESEQUENCE' => array(
			'stats_referer_url_id_seq',
		)),
		array( 'CREATE' => array(
			'stats_referer_users_map' => "
				referer_url_id I4 PRIMARY,
				user_id I4 PRIMARY
				CONSTRAINT '
					, CONSTRAINT `stats_referer_users_url_ref`  FOREIGN KEY (`referer_url_id`) REFERENCES `".BIT_DB_PREFIX."stats_referer_urls` (`referer_url_id`)
					, CONSTRAINT `stats_referer_users_user_ref` FOREIGN KEY (`user_id`) REFERENCES `".BIT_DB_PREFIX."users_users` (`user_id`) '
			",	
		)),
		array( 'CREATEINDEX' => array(
			'stats_referer_map_url_idx'       => array( 'stats_referer_urls', 'referer_url_id', array() ),
			'stats_referer_map_user_idx'       => array( 'stats_referer_urls', 'user_id', array() ),
		)),
		array( 'CREATESEQUENCE' => array(
			'stats_referer_url_id_seq',
		)),
	)),
));
