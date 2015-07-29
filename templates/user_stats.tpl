{* $Header$ *}
<div class="display statistics">
	<div class="header">
		<h1>{tr}Site Registrations{/tr}</h1>
	</div>

	<div class="body">
		<ul class="nav nav-pills">
			{foreach from=$periodHash item=periodName key=periodKey}
			<li role="presentation" class="{if $smarty.request.period==$periodKey} active{/if}"><a href="{$smarty.const.STATS_PKG_URL}users.php?period={$periodKey}">{tr}{$periodName}{/tr}</a></li>
			{/foreach}
		</ul>

		<table class="table">
			<tr>
				<th style="width:20%;" colspan="2">{tr}Period{/tr}</td>
				<th style="width:80%;">{tr}Number of Registrations{/tr}</td>
			</tr>
			{foreach item=reg key=timeframe from=$userStats.per_period}
				<tr class="{cycle values="even,odd"}">
					<td>{$timeframe}</td>
					<td>
					{if $gBitSystem->isFeatureActive( 'stats_referers' ) and $gBitUser->hasPermission( 'p_stats_view_referer' )}
						[<a href="{$smarty.const.STATS_PKG_URL}referers.php?period={$smarty.request.period}&amp;timeframe={$timeframe|urlencode}">Referrers</a>]
					{/if}
					</td>
					<td><div style="width:{math equation="round( ( r / m ) * 100 )" r=$reg m=$userStats.max}%; background:#ff9;padding:0 0 0 5px;">{booticon iname="icon-user"} <a href="{$smarty.const.USERS_PKG_URL}admin/index.php?period={$smarty.request.period}&amp;timeframe={$timeframe|urlencode}">{$reg}</a></div></td>
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
