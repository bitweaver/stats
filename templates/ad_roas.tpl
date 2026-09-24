<div class="display statistics ad-roas">
	<div class="header">
		<h1>{tr}Commerce ROAS vs advertiser ROAS{/tr}</h1>
	</div>
	<div class="body">
		{formfeedback hash=$feedback}

		<p class="help-block">
			{tr}Cost is ad spend for clicks in this date range. Commerce revenue includes every paid order in this range from customers first-touched by the campaign, including people who registered earlier, plus orders after the end date that are still inside the click-through window of a registration in this range. Commerce ROAS is that revenue over this spend. LTV is those customers' paid orders from the start of the range on, with no day cap. Network value is the advertiser's reported conversion value for the same clicks.{/tr}
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
						<th class="text-right">{tr}Commerce revenue{/tr}{if $roasReport.click_window_days} ({$roasReport.click_window_days|escape}d){/if}</th>
						<th class="text-right">{tr}Commerce ROAS{/tr}{if $roasReport.click_window_days} ({$roasReport.click_window_days|escape}d){/if}</th>
						<th class="text-right">{$roasReport.network_label|escape} {tr}value{/tr}</th>
						<th class="text-right">{$roasReport.network_label|escape} {tr}ROAS{/tr}</th>
						<th class="text-right">{$roasReport.network_label|escape} {tr}target{/tr}</th>
						<th class="text-right">{tr}Orders{/tr}</th>
						<th class="text-right">{tr}LTV{/tr}</th>
						<th class="text-right">{tr}LTV ROAS{/tr}</th>
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
							<td class="text-right text-muted">{if $gCommerceCurrencies}{$gCommerceCurrencies->format($row.network_value)}{else}{$row.network_value|string_format:"%.2f"}{/if}</td>
							<td class="text-right text-muted">{if $row.spend > 0}{$row.network_roas|string_format:"%.2f"}x{else}—{/if}</td>
							<td class="text-right">{if $row.target_roas !== null}{$row.target_roas|string_format:"%.2f"}x{else}—{/if}</td>
							<td class="text-right">{$row.orders}</td>
							<td class="text-right">{if $gCommerceCurrencies}{$gCommerceCurrencies->format($row.ltv)}{else}{$row.ltv|string_format:"%.2f"}{/if}</td>
							<td class="text-right">{if $row.spend > 0}{$row.ltv_roas|string_format:"%.2f"}x{else}—{/if}</td>
						</tr>
					{/foreach}
				</tbody>
				<tfoot>
					<tr>
						<th>{tr}Total{/tr}</th>
						<th class="text-right">{if $gCommerceCurrencies}{$gCommerceCurrencies->format($roasReport.totals.spend)}{else}{$roasReport.totals.spend|string_format:"%.2f"}{/if}</th>
						<th class="text-right">{if $gCommerceCurrencies}{$gCommerceCurrencies->format($roasReport.totals.revenue)}{else}{$roasReport.totals.revenue|string_format:"%.2f"}{/if}</th>
						<th class="text-right">{if $roasReport.totals.spend > 0}{$roasReport.totals.commerce_roas|string_format:"%.2f"}x{else}—{/if}</th>
						<th class="text-right text-muted">{if $gCommerceCurrencies}{$gCommerceCurrencies->format($roasReport.totals.network_value)}{else}{$roasReport.totals.network_value|string_format:"%.2f"}{/if}</th>
						<th class="text-right text-muted">{if $roasReport.totals.spend > 0}{$roasReport.totals.network_roas|string_format:"%.2f"}x{else}—{/if}</th>
						<th class="text-right">—</th>
						<th class="text-right">{$roasReport.totals.orders}</th>
						<th class="text-right">{if $gCommerceCurrencies}{$gCommerceCurrencies->format($roasReport.totals.ltv)}{else}{$roasReport.totals.ltv|string_format:"%.2f"}{/if}</th>
						<th class="text-right">{if $roasReport.totals.spend > 0}{$roasReport.totals.ltv_roas|string_format:"%.2f"}x{else}—{/if}</th>
					</tr>
				</tfoot>
			</table>
		{/if}
	</div>
</div>
