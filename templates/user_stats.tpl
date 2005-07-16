{* $Header: /cvsroot/bitweaver/_bit_stats/templates/user_stats.tpl,v 1.1.2.1 2005/07/16 17:55:40 spiderr Exp $ *}
<div class="display statistics">
	<div class="header">
		<h1>{tr}User Stats{/tr} - {tr}Registrations{/tr}</h1>
	</div>
{if $userStats}

	<div class="navbar">
		<a href="{$gTikiLoc.STATS_PKG_URL}users.php?period=86400">{tr}Daily{/tr}</a>
		<a href="{$gTikiLoc.STATS_PKG_URL}users.php?period=604800">{tr}Weekly{/tr}</a>
		<a href="{$gTikiLoc.STATS_PKG_URL}users.php?period=2592000">{tr}30 Days{/tr}</a>
	</div>

	<div class="clear"></div>

	<div class="body">
		<a name="site_stats"></a>
		<table class="data">
			<tr>
				<th>{tr}Time Period{/tr}</th>
				<th>{tr}Registrations{/tr}</th>
				<th>{tr}Graph{/tr}</th>
			</tr>
			{foreach item=reg key=period from=$userStats.per_period}
			<tr class="{cycle values="even,odd"}">
				<td width="9%">{$period|bit_short_date}</td>
				<td width="1%">{$reg}</td>
				<td width="90%"><div style="width:{math equation="round( (r / m) * 100 )" r=$reg m=$userStats.max}%; background:#f80;text-align:left; color:#000;"><small>&nbsp;</small></div></div></td>
			</tr>
			{/foreach}
		</table>

{else}
	<div class="body">
		No results for {$periodName}
{/if}

	</div> <!-- end .body -->
</div> <!-- end .statistics -->
