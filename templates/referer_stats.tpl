<div class="floaticon">{bithelp}</div>

<div class="display statistics">
	<div class="header">
		<h1>{tr}Referer Statistics{/tr}</h1>
	</div>

	<div class="body">
		<a href="{$smarty.const.STATS_PKG_URL}referer_stats.php?clear=1">{tr}Clear Referer Statistics{/tr}</a>

		{minifind}

		<table class="data">
			<caption>{tr}Referer Statistics{/tr}</caption>
			<tr>
				<th style="width:5%;">{smartlink ititle="Hits" isort=hits iorder=desc idefault=1 offset=$offset}</th>
				<th>{smartlink ititle="Referer" isort=referer offset=$offset}</th>
				<th style="width:15%;">{smartlink ititle="Last Hit" isort=last offset=$offset}</th>
			</tr>

			{section name=ix loop=$referers}
				<tr class="{cycle values='odd,even'}">
					<td style="text-align:right;">{$referers[ix].hits}</td>
					<td><a href="{$referers[ix].referer}">{$referers[ix].referer}</a></td>
					<td style="text-align:right;">{$referers[ix].last|bit_short_datetime}</td>
				</tr>
			{sectionelse}
				<tr class="norecords"><td colspan="3">{tr}No records found{/tr}</td></tr>
			{/section}
		</table>
	</div> <!-- end .body -->

	{include file="bitpackage:kernel/pagination.tpl"}
</div> <!-- end .statistics -->
