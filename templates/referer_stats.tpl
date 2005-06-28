<div class="floaticon">{bithelp}</div>

<div class="display statistics">
	<div class="header">
		<h1>{tr}Referer Statistics{/tr}</h1>
	</div>

	<div class="body">
		<table class="data">
			<caption>{tr}Referer Statistics{/tr}</caption>
			<tr>
				<th>{smartlink ititle="Hits" isort=hits iorder=desc idefault=1 offset=$offset}</th>
				<th>{smartlink ititle="Referer" isort=referer offset=$offset}</th>
				<th>{smartlink ititle="Last Hit" isort=last offset=$offset}</th>
			</tr>

			{section name=user loop=$channels}
				<tr class="{cycle values='odd,even'}">
					<td style="text-align:right;">{$channels[user].hits}</td>
					<td>{$channels[user].referer}</td>
					<td style="text-align:right;">{$channels[user].last|bit_short_datetime}</td>
				</tr>
			{sectionelse}
				<tr class="norecords"><td colspan="3">{tr}No records found{/tr}</td></tr>
			{/section}
		</table>
	</div> <!-- end .body -->

	<div class="navbar">
		<a href="{$gBitLoc.STATS_PKG_URL}referer_stats.php?clear=1">{tr}clear stats{/tr}</a>
	</div>

	{include file="bitpackage:kernel/pagination.tpl"}

	{minifind}
</div> <!-- end .statistics -->
