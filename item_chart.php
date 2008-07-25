<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/item_chart.php,v 1.7 2008/07/25 14:57:46 wolff_borg Exp $
 *
 * Copyright (c) 2004 bitweaver.org
 * Copyright (c) 2003 tikwiki.org
 * Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See copyright.txt for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details
 *
 * $Id: item_chart.php,v 1.7 2008/07/25 14:57:46 wolff_borg Exp $
 * @package stats
 * @subpackage functions
 */

/**
 * required setup
 */
require_once( '../bit_setup_inc.php' );
include_once( STATS_PKG_PATH . "Statistics.php" );
include_once( UTIL_PKG_PATH . "phplot.php" );
global $gBitSystem;

$gBitSystem->isPackageActive( 'stats' );
$gBitSystem->verifyPermission( 'p_stats_view' );

$stats = new Statistics();

$data = $stats->getContentTypeChartData( !empty( $_REQUEST['content_type_guid'] ) ? $_REQUEST['content_type_guid'] : NULL );
$chart_type = !empty( $_REQUEST['chart_type'] ) ? $_REQUEST['chart_type'] : 'bars';

// initialise phplot and insert data
$graph =& new PHPlot( 600, 400 * ( count( $data['data'] ) ) );
$graph->SetPrintImage(0);
$graph->SetPlotType( $chart_type );
$graph->SetXTickPos( 'none' );
//$graph->SetYScaleType( 'log' );
$graph->SetTitle( tra( $data['title'] ) );
$graph->SetXLabel( tra( 'Title' ) );

$i = 0;
foreach( $data['data'] as $guid => $info ) {
	$graph->SetDataValues( $info );
	$graph->SetDrawXDataLabels( TRUE );
	$graph->SetXLabelAngle( ( count( $info ) > 5 ) ? 90 : 0 );
	$graph->SetNewPlotAreaPixels( 75, 30 + ( $i * 390 ), 580, 370 + ( $i * 390 ) );
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
