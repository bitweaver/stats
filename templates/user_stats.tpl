{* $Header: /cvsroot/bitweaver/_bit_stats/templates/user_stats.tpl,v 1.11 2006/03/22 10:24:20 squareing Exp $ *}
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
			<caption>{tr}User Registrations at {$gBitSystem->getConfig('site_title')}{/tr}</caption>
			<tr>
				<th style="width:20%;">{tr}Period{/tr}</td>
				<th style="width:80%;">{tr}Number of Registrations{/tr}</td>
			</tr>
			{foreach item=reg key=period from=$userStats.per_period}
				<tr class="{cycle values="even,odd"}">
					<td>{$period}</td>
					<td><div style="width:{math equation="round( ( r / m ) * 100 )" r=$reg m=$userStats.max}%; background:#f80;padding:0 0 0 5px;">{$reg}</div></td>
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
