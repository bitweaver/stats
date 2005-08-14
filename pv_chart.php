<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/pv_chart.php,v 1.1.1.1.2.2 2005/08/14 12:02:39 wolff_borg Exp $
 *
 * Copyright (c) 2004 bitweaver.org
 * Copyright (c) 2003 tikwiki.org
 * Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See copyright.txt for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details
 *
 * $Id: pv_chart.php,v 1.1.1.1.2.2 2005/08/14 12:02:39 wolff_borg Exp $
 * @package stats
 * @subpackage functions
 */

/**
 * required setup
 */
require_once( '../bit_setup_inc.php' );
//Include the code
include_once( STATS_PKG_PATH . "stats_lib.php" );
include_once( UTIL_PKG_PATH . "phplot.php" );
global $gBitSystem;

$gBitSystem->isPackageActive( 'stats' );
$gBitSystem->verifyPermission( 'bit_p_view_stats' );

//Define the object
$graph = new PHPlot;

//Set some data
if (!isset($_REQUEST["days"]))
	$_REQUEST["days"] = 7;

$example_data = $statslib->get_pv_chart_data($_REQUEST["days"]);
$graph->SetDataValues($example_data);
//$graph->SetPlotType('bars');
$graph->SetPlotType('lines');
$graph->SetYLabel(tra('pageviews'));
$graph->SetXLabel(tra('day')); 
//Draw it
$graph->DrawGraph();

?>
