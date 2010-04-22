<?php

$tables = array(

'stats_referers' => "
	referer C(50) NOTNULL,
	hits I8,
	last I8
",

'stats_pageviews' => "
	stats_day I8 PRIMARY,
	pageviews I8
",

'stats_referer_urls' => "
	referer_url_id I4 PRIMARY,
	referer_url X NOTNULL
",

'stats_referer_users_map' => "
	referer_url_id I4 PRIMARY,
	user_id I4 PRIMARY
	CONSTRAINT '
		, CONSTRAINT `stats_referer_users_url_ref`  FOREIGN KEY (`referer_url_id`) REFERENCES `".BIT_DB_PREFIX."stats_referer_urls` (`referer_url_id`)
		, CONSTRAINT `stats_referer_users_user_ref` FOREIGN KEY (`user_id`) REFERENCES `".BIT_DB_PREFIX."users_users` (`user_id`) '
",	
);

global $gBitInstaller;

foreach( array_keys( $tables ) AS $tableName ) {
	$gBitInstaller->registerSchemaTable( STATS_PKG_NAME, $tableName, $tables[$tableName] );
}

$gBitInstaller->registerPackageInfo( STATS_PKG_NAME, array(
	'description' => "Stats collects and display information about your site.",
	'license' => '<a href="http://www.gnu.org/licenses/licenses.html#LGPL">LGPL</a>',
) );

// ### Indexes
$indices = array (
	'stats_referer_map_url_idx' => array( 'stats_referer_urls', 'referer_url_id', array() ),
	'stats_referer_map_user_idx' => array( 'stats_referer_urls', 'user_id', array() ),
);
$gBitInstaller->registerSchemaIndexes( STATS_PKG_NAME, $indices );

// ### Sequences
$sequences = array (
	'stats_referer_url_id_seq' => array( 'start' => 1 )
);
$gBitInstaller->registerSchemaSequences( STATS_PKG_NAME, $sequences );

// ### Default Preferences
$gBitInstaller->registerPreferences( STATS_PKG_NAME, array(
	array(STATS_PKG_NAME, 'stats_referers','y'),
	array(STATS_PKG_NAME, 'stats_pageviews','y')
) );

// ### Default UserPermissions
$gBitInstaller->registerUserPermissions( STATS_PKG_NAME, array(
	array('p_stats_view_referer', 'Can view referer stats', 'editors', STATS_PKG_NAME),
	array('p_stats_view', 'Can view site stats', 'basic', STATS_PKG_NAME),
) );

// Requirements
$gBitInstaller->registerRequirements( STATS_PKG_NAME, array(
    'liberty' => array( 'min' => '2.1.4' ),
));
