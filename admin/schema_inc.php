<?php

$tables = array(

'tiki_referer_stats' => "
	referer C(50) NOTNULL,
	hits I8, 
	last I8
"

);

global $gBitInstaller;

foreach( array_keys( $tables ) AS $tableName ) {
	$gBitInstaller->registerSchemaTable( STATS_PKG_NAME, $tableName, $tables[$tableName] );
}

$gBitInstaller->registerPackageInfo( STATS_PKG_NAME, array(
	'description' => "Stats collects and display information about your site.",
	'license' => '<a href="http://www.gnu.org/licenses/licenses.html#LGPL">LGPL</a>',
	'version' => '0.1',
	'state' => 'stable',
	'dependencies' => '',
) );

// ### Default Preferences
$gBitInstaller->registerPreferences( STATS_PKG_NAME, array(
	array('', 'feature_referer_stats','y')
) );

// ### Default UserPermissions
$gBitInstaller->registerUserPermissions( STATS_PKG_NAME, array(
	array('bit_p_view_referer_stats', 'Can view referer stats', 'editors', STATS_PKG_NAME),
	array('bit_p_view_stats', 'Can view site stats', 'basic', STATS_PKG_NAME),
) );


?>
