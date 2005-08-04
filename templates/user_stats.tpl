{* $Header: /cvsroot/bitweaver/_bit_stats/templates/user_stats.tpl,v 1.1.2.4 2005/08/04 08:46:42 squareing Exp $ *}
<div class="display statistics">
	<div class="header">
		<h1>{tr}Site Registrations{/tr}</h1>
	</div>

	<div class="body">
		<div class="navbar">
			<ul>
				<li><a href="{$gTikiLoc.STATS_PKG_URL}users.php?period=86400">{tr}Daily{/tr}</a></li>
				<li><a href="{$gTikiLoc.STATS_PKG_URL}users.php?period=604800">{tr}Weekly{/tr}</a></li>
				<li><a href="{$gTikiLoc.STATS_PKG_URL}users.php?period=2592000">{tr}30 Days{/tr}</a></li>
			</ul>
		</div>

		<table class="clear data">
			<caption>{tr}User Registrations at {$siteTitle}{/tr}</caption>
			<tr>
				<th style="width:20%;">{tr}Period{/tr}</td>
				<th style="width:80%;">{tr}Number of Registrations{/tr}</td>
			</tr>
			{foreach item=reg key=period from=$userStats.per_period}
				<tr class="{cycle values="even,odd"}">
					<td>{$period|bit_short_date}</td>
					<td><div style="width:{math equation="round( ( r / m ) * 100 )" r=$reg m=$userStats.max}%; background:#f80;padding:0 0 0 5px;">{$reg}</div></td>
				</tr>
			{foreachelse}
				<tr class="norecords">
					<td colspan="2">
						{tr}No results for {$periodName}{/tr}
					</td>
				</tr>
			{/foreach}
		</table>
	</div> <!-- end .body -->
</div> <!-- end .statistics -->
