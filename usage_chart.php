<?php
/**
 * $Header: /cvsroot/bitweaver/_bit_stats/usage_chart.php,v 1.2 2005/06/28 07:45:58 spiderr Exp $
 *
 * Copyright (c) 2004 bitweaver.org
 * Copyright (c) 2003 tikwiki.org
 * Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See copyright.txt for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details
 *
 * $Id: usage_chart.php,v 1.2 2005/06/28 07:45:58 spiderr Exp $
 * @package stats
 * @subpackage functions
 */

/**
 * required setup
 */
require_once( '../bit_setup_inc.php' );
require_once (STATS_PKG_PATH."stats_lib.php");
//Include the code
require (UTIL_PKG_PATH."phplot.php");


if ($feature_stats != 'y') {
	die;
}

if ($bit_p_view_stats != 'y') {
	die;
}

//Define the object
$graph = new PHPlot;
//Set some data
$example_data = $statslib->get_usage_chart_data();
$graph->SetDataValues($example_data);
$graph->SetPlotType('bars');
//$graph->SetPlotType('lines');
//Draw it
$graph->DrawGraph();

?>
