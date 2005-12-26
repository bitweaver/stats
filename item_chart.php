<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/item_chart.php,v 1.2 2005/12/26 12:26:04 squareing Exp $
 *
 * Copyright (c) 2004 bitweaver.org
 * Copyright (c) 2003 tikwiki.org
 * Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See copyright.txt for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details
 *
 * $Id: item_chart.php,v 1.2 2005/12/26 12:26:04 squareing Exp $
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
$data = $statslib->get_item_chart_data( !empty( $_REQUEST['content_type_guid'] ) ? $_REQUEST['content_type_guid'] : NULL );
$chart_type = !empty( $_REQUEST['chart_type'] ) ? $_REQUEST['chart_type'] : 'points';

if( !empty( $_REQUEST['content_type_guid'] ) ) {
	$data['data'] = $data['data'][$_REQUEST['content_type_guid']];
}
// initialise phplot and insert data
$graph =& new PHPlot( 600, 400 * ( count( $data['data'] ) ) );
$graph->SetPrintImage(0);
$graph->SetPlotType( $chart_type );
$graph->SetXTickPos( 'none' );
$graph->SetYScaleType( 'log' );
$graph->SetTitle( tra( $data['title'] ) );
$graph->SetXLabel( tra( 'Title' ) );

$i = 0;
foreach( $data['data'] as $guid => $info ) {
	$graph->SetDataValues( $info );
	$graph->SetDrawXDataLabels( TRUE );
	$graph->SetXLabelAngle( ( count( $info ) > 5 ) ? 90 : 0 );
	$graph->SetNewPlotAreaPixels( 75, 30 + ( $i * 390 ), 580, 280 + ( $i * 390 ) );
	if( !empty( $_REQUEST['content_type_guid'] ) ) {
		$graph->SetYLabel( $gLibertySystem->mContentTypes[$_REQUEST['content_type_guid']]['content_description'].' '.tra( 'Hits' ).' ('.tra( "log" ).')' );
	} else {
		$graph->SetYLabel( $gLibertySystem->mContentTypes[$guid]['content_description'].' '.tra( 'Hits' ).' ('.tra( "log" ).')' );
	}
	$graph->DrawGraph();
	$i++;
}
$graph->PrintImage();
?>
