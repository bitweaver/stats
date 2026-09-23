<div class="display statistics ad-roas">
	<div class="header">
		<h1>{tr}Commerce ROAS vs advertiser ROAS{/tr}</h1>
	</div>
	<div class="body">
		{formfeedback hash=$feedback}

		<p class="help-block">
			{tr}Cost is ad spend in this date range. Commerce revenue is orders from users first-touched in the same range — not later purchases by people acquired years earlier. Click-window Commerce ROAS only counts those new users' orders within the network's click lookback (for setting target ROAS). Network ROAS is the advertiser's reported conversion value over spend (their attribution; tROAS is a bid target). Registration-cohort LTV is this store's lifetime order totals for those new users over the same spend.{/tr}
		</p>

		<form class="form-inline" method="get" action="{$smarty.const.STATS_PKG_URL}ad_roas.php">
			<label class="sr-only" for="roas-since">{tr}Since{/tr}</label>
			<input id="roas-since" class="form-control" type="date" name="since" value="{$roasSince|escape}" />
			<span>{tr}to{/tr}</span>
			<label class="sr-only" for="roas-until">{tr}Until{/tr}</label>
			<input id="roas-until" class="form-control" type="date" name="until" value="{$roasUntil|escape}" />
			{if $roasNetworks}
				<label class="sr-only" for="roas-network">{tr}Network{/tr}</label>
				<select id="roas-network" class="form-control" name="network">
					{foreach from=$roasNetworks key=code item=label}
						<option value="{$code|escape}" {if $roasNetwork eq $code}selected="selected"{/if}>{$label|escape}</option>
					{/foreach}
				</select>
			{/if}
			<label class="checkbox">
				<input type="checkbox" name="cohort" value="1" {if $roasCohort}checked="checked"{/if} />
				{tr}Registration-cohort LTV{/tr}
			</label>
			<button type="submit" class="btn btn-default">{tr}Update{/tr}</button>
			{if $roasReport}
				<button type="submit" class="btn btn-default" name="download" value="1">{tr}CSV{/tr}</button>
			{/if}
		</form>

		{if $roasWindowAsk}
			<form class="form-inline" method="post" action="{$smarty.const.STATS_PKG_URL}ad_roas.php" style="margin-top:1em">
				<input type="hidden" name="tk" value="{$gBitUser->mTicket|escape}" />
				<input type="hidden" name="since" value="{$roasSince|escape}" />
				<input type="hidden" name="until" value="{$roasUntil|escape}" />
				<input type="hidden" name="network" value="{$roasNetwork|escape}" />
				{if $roasCohort}<input type="hidden" name="cohort" value="1" />{/if}
				<p>
					{tr}Click-through conversion window for{/tr}
					<strong>{if $roasReport}{$roasReport.network_label|escape}{else}{$roasNetwork|escape}{/if}</strong>
					{tr}is not stored. Enter the lookback the advertiser uses (days):{/tr}
				</p>
				<input class="form-control" type="number" name="click_window_days" min="1" max="90" value="90" />
				<button type="submit" class="btn btn-default" name="save_window" value="1">{tr}Save window{/tr}</button>
			</form>
		{elseif $roasReport && $roasReport.click_window_days}
			<p>
				{$roasReport.network_label|escape} {tr}click-through window:{/tr}
				<strong>{$roasReport.click_window_days|escape} {tr}days{/tr}</strong>
			</p>
		{/if}

		{if $roasReport}
			<table class="table table-condensed table-striped">
				<caption>
					{$roasReport.network_label|escape}
					{$roasReport.since|escape} – {$roasReport.until|escape}
					({$roasReport.rows|@count} {tr}campaigns{/tr})
				</caption>
				<thead>
					<tr>
						<th>{tr}Campaign{/tr}</th>
						<th class="text-right">{tr}Spend{/tr}</th>
						<th class="text-right">{tr}Commerce revenue{/tr}</th>
						<th class="text-right">{tr}Commerce ROAS{/tr}</th>
						{if $roasReport.click_window_days}
							<th class="text-right">{tr}Commerce{/tr} ({$roasReport.click_window_days|escape}d)</th>
							<th class="text-right">{tr}Commerce ROAS{/tr} ({$roasReport.click_window_days|escape}d)</th>
						{/if}
						<th class="text-right">{$roasReport.network_label|escape} {tr}value{/tr}</th>
						<th class="text-right">{$roasReport.network_label|escape} {tr}ROAS{/tr}</th>
						<th class="text-right">{$roasReport.network_label|escape} {tr}target{/tr}</th>
						<th class="text-right">{tr}Orders{/tr}</th>
						{if $roasCohort}
							<th class="text-right">{tr}Cohort regs{/tr}</th>
							<th class="text-right">{tr}Cohort buyers{/tr}</th>
							<th class="text-right">{tr}Cohort LTV{/tr}</th>
							<th class="text-right">{tr}Cohort LTV ROAS{/tr}</th>
						{/if}
					</tr>
				</thead>
				<tbody>
					{foreach from=$roasReport.rows item=row}
						<tr>
							<td>
								{if $row.campaign_id}
									<code>{$row.campaign_id|escape}</code>
								{else}
									<span class="text-muted">{tr}name only{/tr}</span>
								{/if}
								{$row.campaign_name|escape}
							</td>
							<td class="text-right">{if $gCommerceCurrencies}{$gCommerceCurrencies->format($row.spend)}{else}{$row.spend|string_format:"%.2f"}{/if}</td>
							<td class="text-right">{if $gCommerceCurrencies}{$gCommerceCurrencies->format($row.revenue)}{else}{$row.revenue|string_format:"%.2f"}{/if}</td>
							<td class="text-right">{if $row.spend > 0}{$row.commerce_roas|string_format:"%.2f"}x{else}—{/if}</td>
							{if $roasReport.click_window_days}
								<td class="text-right">{if $gCommerceCurrencies}{$gCommerceCurrencies->format($row.lookback_revenue)}{else}{$row.lookback_revenue|string_format:"%.2f"}{/if}</td>
								<td class="text-right">{if $row.spend > 0}{$row.lookback_roas|string_format:"%.2f"}x{else}—{/if}</td>
							{/if}
							<td class="text-right text-muted">{if $gCommerceCurrencies}{$gCommerceCurrencies->format($row.network_value)}{else}{$row.network_value|string_format:"%.2f"}{/if}</td>
							<td class="text-right text-muted">{if $row.spend > 0}{$row.network_roas|string_format:"%.2f"}x{else}—{/if}</td>
							<td class="text-right">{if $row.target_roas !== null}{$row.target_roas|string_format:"%.2f"}x{else}—{/if}</td>
							<td class="text-right">{$row.orders}</td>
							{if $roasCohort}
								<td class="text-right">{$row.cohort_regs}</td>
								<td class="text-right">{$row.cohort_buyers}</td>
								<td class="text-right">{if $gCommerceCurrencies}{$gCommerceCurrencies->format($row.cohort_ltv)}{else}{$row.cohort_ltv|string_format:"%.2f"}{/if}</td>
								<td class="text-right">{if $row.spend > 0}{$row.cohort_ltv_roas|string_format:"%.2f"}x{else}—{/if}</td>
							{/if}
						</tr>
					{/foreach}
				</tbody>
				<tfoot>
					<tr>
						<th>{tr}Total{/tr}</th>
						<th class="text-right">{if $gCommerceCurrencies}{$gCommerceCurrencies->format($roasReport.totals.spend)}{else}{$roasReport.totals.spend|string_format:"%.2f"}{/if}</th>
						<th class="text-right">{if $gCommerceCurrencies}{$gCommerceCurrencies->format($roasReport.totals.revenue)}{else}{$roasReport.totals.revenue|string_format:"%.2f"}{/if}</th>
						<th class="text-right">{if $roasReport.totals.spend > 0}{$roasReport.totals.commerce_roas|string_format:"%.2f"}x{else}—{/if}</th>
						{if $roasReport.click_window_days}
							<th class="text-right">{if $gCommerceCurrencies}{$gCommerceCurrencies->format($roasReport.totals.lookback_revenue)}{else}{$roasReport.totals.lookback_revenue|string_format:"%.2f"}{/if}</th>
							<th class="text-right">{if $roasReport.totals.spend > 0}{$roasReport.totals.lookback_roas|string_format:"%.2f"}x{else}—{/if}</th>
						{/if}
						<th class="text-right text-muted">{if $gCommerceCurrencies}{$gCommerceCurrencies->format($roasReport.totals.network_value)}{else}{$roasReport.totals.network_value|string_format:"%.2f"}{/if}</th>
						<th class="text-right text-muted">{if $roasReport.totals.spend > 0}{$roasReport.totals.network_roas|string_format:"%.2f"}x{else}—{/if}</th>
						<th class="text-right">—</th>
						<th class="text-right">{$roasReport.totals.orders}</th>
						{if $roasCohort}
							<th class="text-right">{$roasReport.totals.cohort_regs}</th>
							<th class="text-right">{$roasReport.totals.cohort_buyers}</th>
							<th class="text-right">{if $gCommerceCurrencies}{$gCommerceCurrencies->format($roasReport.totals.cohort_ltv)}{else}{$roasReport.totals.cohort_ltv|string_format:"%.2f"}{/if}</th>
							<th class="text-right">{if $roasReport.totals.spend > 0}{$roasReport.totals.cohort_ltv_roas|string_format:"%.2f"}x{else}—{/if}</th>
						{/if}
					</tr>
				</tfoot>
			</table>
		{/if}
	</div>
</div>
