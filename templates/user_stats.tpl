{* $Header: /cvsroot/bitweaver/_bit_stats/templates/user_stats.tpl,v 1.2 2005/07/17 17:36:17 squareing Exp $ *}
<div class="display statistics">
	<div class="header">
		<h1>{tr}Site Registrations{/tr}</h1>
	</div>

	<div class="navbar">
		<ul>
			<li><a href="{$gTikiLoc.STATS_PKG_URL}users.php?period=86400">{tr}Daily{/tr}</a></li>
			<li><a href="{$gTikiLoc.STATS_PKG_URL}users.php?period=604800">{tr}Weekly{/tr}</a></li>
			<li><a href="{$gTikiLoc.STATS_PKG_URL}users.php?period=2592000">{tr}30 Days{/tr}</a></li>
		</ul>
	</div>

	<div class="clear"></div>

	<div class="body">
		<ul class="data">
			{foreach item=reg key=period from=$userStats.per_period}
				<li class="item {cycle values="even,odd"}">
					{$period|bit_long_date}
					{tr}{$reg} registrations{/tr}
					<div style="width:{math equation="round( (r / m) * 100 )" r=$reg m=$userStats.max}%; background:#f80;height:20px;"></div>
				</li>
			{foreachelse}
				<li class="norecords">
					No results for {$periodName}
				</li>
			{/foreach}
		</ul>
	</div> <!-- end .body -->
</div> <!-- end .statistics -->
