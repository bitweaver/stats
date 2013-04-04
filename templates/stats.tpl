{* $Header$ *}
<div class="display statistics">
	<div class="header">
		<h1>{tr}Stats{/tr}</h1>
	</div>

	<div class="body">
		<table class="data">
			<caption>{tr}Global Statistics{/tr}</caption>
			<tr class="{cycle values="odd,even"}"><td>{tr}Started{/tr}</td><td style="text-align:right;">{$siteStats.started|bit_short_date}</td></tr>
			<tr class="{cycle}"><td>{tr}Days online{/tr}</td><td style="text-align:right;">{$siteStats.days}</td></tr>
			<tr class="{cycle}"><td>{tr}Total pageviews{/tr}</td><td style="text-align:right;">{$siteStats.pageviews}</td></tr>
			<tr class="{cycle}"><td>{tr}Average pageviews per day{/tr}</td><td style="text-align:right;">{$siteStats.ppd|string_format:"%.2f"}</td></tr>
			<tr class="{cycle}"><td>{tr}Best day{/tr}</td><td style="text-align:right;">{$siteStats.bestday|bit_short_date} ({$siteStats.bestpvs} {tr}pageviews{/tr})</td></tr>
			<tr class="{cycle}"><td>{tr}Worst day{/tr}</td><td style="text-align:right;">{$siteStats.worstday|bit_short_date} ({$siteStats.worstpvs} {tr}pageviews{/tr})</td></tr>
		</table>

		<br /><hr /><br />

		<table class="data">
			<caption>{tr}Site Overview{/tr}</caption>
			<tr>
				<th>{smartlink ititle="Content Type" isort=content_type_guid}</th>
				<th style="width:10%;">{smartlink ititle="# of Records" iorder=desc isort=content_count}</th>
				<th style="width:10%;">{smartlink ititle="# of Hits" iorder=desc isort=total_hits}</th>
			</tr>

			{foreach from=$contentOverview item=site key=guid}
				<tr class="{cycle values="odd,even"}">
					<td>{if $contentStats.$guid}<a href="#{$guid}">{/if}{$gLibertySystem->getContentTypeName($guid)}{if $contentStats.$guid}</a>{/if}</td>
					<td style="text-align:right;">{$site.content_count}</td>
					<td style="text-align:right;">{$site.total_hits|default:0}</td>
				</tr>
			{/foreach}
		</table>

		<br /><hr /><br />

		<table class="data">
			<caption>{tr}Package Statistics{/tr}</caption>
			{foreach from=$contentStats item=stats key=guid}
				<tr>
					<th colspan="2">{$gLibertySystem->getContentTypeName($guid)}</th>
				</tr>
				<a name="{$guid}"></a>
				{foreach from=$stats item=item}
					<tr class="{cycle values="odd,even"}">
						<td>{$item.label}</td>
						<td style="width:20%; text-align:right;">
							{if $item.modifier == 'display_bytes'}
								{$item.value|display_bytes}
							{else}
								{$item.value}
							{/if}
						</td>
					</tr>
				{/foreach}
			{/foreach}
		</table>

		<br /><hr /><br />

		<h1>{tr}Graph options{/tr}</h1>
		{legend legend="Individual Package Statistics"}
			<div class="control-group">
				{formlabel label="Item Statistics"}
				{forminput}
					{smartlink ititle=All chart_type=points item_chart=1 ianchor="item_chart"}
					<br />
					{foreach from=$gLibertySystem->mContentTypes item=contentType}
						{smartlink ititle=$contentType.content_name content_type_guid=$contentType.content_type_guid chart_type=points item_chart=1 ianchor="item_chart"}
						<br />
					{/foreach}
					{formhelp note="Please note that these graphs use a logarythmic y-axis."}
				{/forminput}
			</div>
		{/legend}

		{if $smarty.request.item_chart}
			<a name="item_chart"></a>
			<div style="text-align:center;">
				<img src="{$smarty.const.STATS_PKG_URL}item_chart.php?content_type_guid={$smarty.request.content_type_guid}" alt="{tr}Usage chart image{/tr}" />
			</div>
		{/if}

		{legend legend="Usage Statistics"}
			<div class="control-group">
				{formlabel label="Usage Statistics"}
				{forminput}
					{smartlink ititle="Display as Pie-chart" chart_type=pie usage_chart=1 ianchor="usage_chart"}
					<br />
					{smartlink ititle="Display as Bar-chart" chart_type=bars usage_chart=1 ianchor="usage_chart"}
				{/forminput}
			</div>
		{/legend}

		{if $smarty.request.usage_chart}
			<a name="usage_chart"></a>
			<div style="text-align:center;">
				<img src="{$smarty.const.STATS_PKG_URL}usage_chart.php?chart_type={$smarty.request.chart_type}" alt="{tr}Usage chart image{/tr}" />
			</div>
		{/if}

		{form legend="Site Usage Chart" ianchor="pv_chart"}
			<div class="control-group">
				{formlabel label="Stats Period" for="days"}
				{forminput}
					<input type="text" name="days" id="days" size="5" value="{$smarty.request.days}" /> {tr}days{/tr}
					{formhelp note="Number of days you want the graph to include. Insert 0 for full duration of your site."}
				{/forminput}
			</div>

			<div class="control-group submit">
				<input type="submit" class="btn" name="pv_chart" value="{tr}Display{/tr}" />
			</div>
		{/form}

		{if $smarty.request.pv_chart}
			<a name="pv_chart"></a>
			<div style="text-align:center;">
				<img src="{$smarty.const.STATS_PKG_URL}pv_chart.php?days={$smarty.request.days}" alt="Site Usage Statistics" />
			</div>
		{/if}
	</div> <!-- end .body -->
</div> <!-- end .statistics -->
