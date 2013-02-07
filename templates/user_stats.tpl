{* $Header$ *}
<div class="display statistics">
	<div class="header">
		<h1>{tr}Site Registrations{/tr}</h1>
	</div>

	<div class="body">
		<div class="navbar">
			<ul>
				<li><a href="{$smarty.const.STATS_PKG_URL}users.php?period=day">{tr}Daily{/tr}</a></li>
				<li><a href="{$smarty.const.STATS_PKG_URL}users.php?period=week">{tr}Weekly{/tr}</a></li>
				<li><a href="{$smarty.const.STATS_PKG_URL}users.php?period=month">{tr}Monthly{/tr}</a></li>
				<li><a href="{$smarty.const.STATS_PKG_URL}users.php?period=quarter">{tr}Quarterly{/tr}</a></li>
				<li><a href="{$smarty.const.STATS_PKG_URL}users.php?period=year">{tr}Yearly{/tr}</a></li>
			</ul>
		</div>

		<table class="clear data">
			<caption>{$gBitSystem->getConfig('site_title')} {tr}User Registrations{/tr}</caption>
			<tr>
				<th style="width:20%;" colspan="2">{tr}Period{/tr}</td>
				<th style="width:80%;">{tr}Number of Registrations{/tr}</td>
			</tr>
			{foreach item=reg key=timeframe from=$userStats.per_period}
				<tr class="{cycle values="even,odd"}">
					<td><a href="{$smarty.const.STATS_PKG_URL}users.php?period={$smarty.request.period}&amp;timeframe={$timeframe|urlencode}">{$timeframe}</td>
					<td>
						[<a href="{$smarty.const.STATS_PKG_URL}referer_stats.php?period={$smarty.request.period}&amp;timeframe={$timeframe|urlencode}">Referrers</a>]
					</td>
					<td><div style="width:{math equation="round( ( r / m ) * 100 )" r=$reg m=$userStats.max}%; background:#ff9;padding:0 0 0 5px;"><a href="{$smarty.const.USERS_PKG_URL}admin/index.php?period={$smarty.request.period}&amp;timeframe={$timeframe|urlencode}">{$reg}</a></div></td>
				</tr>
			{foreachelse}
				<tr class="norecords">
					<td colspan="2">
						{tr}No results{/tr}
					</td>
				</tr>
			{/foreach}
		</table>
	</div> <!-- end .body -->
</div> <!-- end .statistics -->
