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

);

global $gBitInstaller;

foreach( array_keys( $tables ) AS $tableName ) {
	$gBitInstaller->registerSchemaTable( STATS_PKG_NAME, $tableName, $tables[$tableName] );
}

$gBitInstaller->registerPackageInfo( STATS_PKG_NAME, array(
	'description' => "Stats collects and display information about your site.",
	'license' => '<a href="http://www.gnu.org/licenses/licenses.html#LGPL">LGPL</a>',
) );

// ### Default Preferences
$gBitInstaller->registerPreferences( STATS_PKG_NAME, array(
	array(STATS_PKG_NAME, 'stats_referers','y')
) );

// ### Default UserPermissions
$gBitInstaller->registerUserPermissions( STATS_PKG_NAME, array(
	array('p_stats_view_referer', 'Can view referer stats', 'editors', STATS_PKG_NAME),
	array('p_stats_view', 'Can view site stats', 'basic', STATS_PKG_NAME),
) );


?>
