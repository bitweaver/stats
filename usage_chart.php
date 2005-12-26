<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/usage_chart.php,v 1.1.1.1.2.3 2005/12/26 08:04:37 squareing Exp $
 *
 * Copyright (c) 2004 bitweaver.org
 * Copyright (c) 2003 tikwiki.org
 * Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See copyright.txt for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details
 *
 * $Id: usage_chart.php,v 1.1.1.1.2.3 2005/12/26 08:04:37 squareing Exp $
 * @package stats
 * @subpackage functions
 */

/**
 * required setup
 */
require_once( '../bit_setup_inc.php' );
include_once( STATS_PKG_PATH . "stats_lib.php" );
include_once( UTIL_PKG_PATH . "phplot.php" );
global $gBitSystem;

$gBitSystem->isPackageActive( 'stats' );
$gBitSystem->verifyPermission( 'bit_p_view_stats' );

// data to be displayed
$data = $statslib->get_usage_chart_data();

$chart_type = !empty( $_REQUEST['chart_type'] ) ? $_REQUEST['chart_type'] : 'bars';

// initialise phplot and insert data
$graph =& new PHPlot( 600, 400 );
$graph->SetTitle( tra( 'Site Usage Statistics' ) );
$graph->SetPlotType( $chart_type );
if( $chart_type == 'pie' ) {
	$graph->SetShading( 20 );
	$graph->SetLegendPixels( 1, 30, FALSE );
	$graph->SetLegend( $data['legend'] );
	$graph->SetDataValues( $data['data'] );
} else {
	array_shift( $data['data'][0] );
	foreach( $data['data'][0] as $key => $item ) {
		$bars[] = array( $data['legend'][$key], $item );
	}
	$graph->SetShading( 7 );
	$graph->SetDataValues( $bars );
	$graph->SetDrawXDataLabels( TRUE );
	$graph->SetXLabelAngle( ( count( $bars ) > 5 ) ? 90 : 0 );
	$graph->SetXTickPos('none');
}
$graph->DrawGraph();
?>
