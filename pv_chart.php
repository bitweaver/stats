<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/pv_chart.php,v 1.1.1.1.2.3 2005/12/25 21:30:59 squareing Exp $
 *
 * Copyright (c) 2004 bitweaver.org
 * Copyright (c) 2003 tikwiki.org
 * Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See copyright.txt for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details
 *
 * $Id: pv_chart.php,v 1.1.1.1.2.3 2005/12/25 21:30:59 squareing Exp $
 * @package stats
 * @subpackage functions
 */

/**
 * required setup
 */
require_once( '../bit_setup_inc.php' );
//Include the code
include_once( STATS_PKG_PATH . "stats_lib.php" );
include_once( UTIL_PKG_PATH . "phplot/phplot.php" );
global $gBitSystem;

$gBitSystem->isPackageActive( 'stats' );
$gBitSystem->verifyPermission( 'bit_p_view_stats' );

$days = isset( $_REQUEST["days"] ) ? $_REQUEST['days'] : 7;
$data = $statslib->get_pv_chart_data( $days );

// initialise phplot and insert data
$graph =& new PHPlot( 600, 600 );
$graph->SetDataValues( $data );
$graph->SetTitle( tra( 'Total Pageviews' ) );
$graph->SetYTitle( tra( 'Pageviews' ) );
$graph->SetXTitle( tra( 'Days') ); 
$graph->SetPlotType( ( count( $data ) > 50 ) ? 'lines' : 'linepoints' );
$graph->SetDrawXDataLabels( TRUE );
$graph->SetXLabelAngle( 90 );
$graph->SetXTickPos('none');
$graph->DrawGraph();
?>
