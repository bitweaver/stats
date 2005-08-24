<div class="floaticon">{bithelp}</div>

<div class="display statistics">
	<div class="header">
		<h1>{tr}Referer Statistics{/tr}</h1>
	</div>

	<div class="body">
		{minifind}

		<table class="data">
			<caption>{tr}Referer Statistics{/tr}</caption>
			<tr>
				<th>{smartlink ititle="Hits" isort=hits iorder=desc idefault=1 offset=$offset}</th>
				<th>{smartlink ititle="Referer" isort=referer offset=$offset}</th>
				<th>{smartlink ititle="Last Hit" isort=last offset=$offset}</th>
			</tr>

			{section name=user loop=$referers}
				<tr class="{cycle values='odd,even'}">
					<td style="text-align:right;">{$referers[user].hits}</td>
					<td>{$referers[user].referer}</td>
					<td style="text-align:right;">{$referers[user].last|bit_short_datetime}</td>
				</tr>
			{sectionelse}
				<tr class="norecords"><td colspan="3">{tr}No records found{/tr}</td></tr>
			{/section}
		</table>

		<a href="{$smarty.const.STATS_PKG_URL}referer_stats.php?clear=1">{tr}clear stats{/tr}</a>
	</div> <!-- end .body -->

	{include file="bitpackage:kernel/pagination.tpl"}
</div> <!-- end .statistics -->
