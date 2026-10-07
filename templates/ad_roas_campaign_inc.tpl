{* Campaign drill-down for ad_roas.tpl; expects $roasDetail, $roasReport, $roasBaseQuery and the roas_* template functions. *}
{assign var=camp value=$roasDetail.campaign}
<div class="roas-campaign" id="roas-campaign">
	<h2>
		{$camp.campaign_name|escape}
		<small><code>{$camp.campaign_id|escape}</code>
			{if $camp.channel}· {$camp.channel|escape|replace:'_':' '|lower}{/if}
			{if $camp.status}· {$camp.status|escape|lower}{/if}
			· <a href="{$smarty.const.STATS_PKG_URL}ad_roas.php?{$roasBaseQuery}">{tr}close{/tr}</a>
		</small>
	</h2>

	<div class="row">
		<div class="col-md-5">
			<h3>{tr}Settings history{/tr}</h3>
			{if $roasDetail.target_history}
				<table class="table table-condensed roas-table">
					<thead>
						<tr>
							<th>{tr}From{/tr}</th>
							<th>{tr}Bidding{/tr}</th>
							<th class="text-right">{tr}Target{/tr}</th>
							<th class="text-right">{tr}tCPA{/tr}</th>
							<th class="text-right">{tr}Budget/day{/tr}</th>
							<th>{tr}Status{/tr}</th>
						</tr>
					</thead>
					<tbody>
						{foreach from=$roasDetail.target_history item=h}
							<tr>
								<td>{$h.snapshot_date|escape|truncate:10:""}</td>
								<td>{if $h.bidding_strategy_type}{$h.bidding_strategy_type|escape|replace:'_':' '|lower}{/if}{if $h.bidding_scope eq 'portfolio'} <span class="roas-sub">({$h.bidding_strategy_name|escape})</span>{/if}</td>
								<td class="text-right">{call roas_x v=$h.target_roas}</td>
								<td class="text-right">{if $h.target_cpa}{call roas_money v=$h.target_cpa}{else}<span class="text-muted">—</span>{/if}</td>
								<td class="text-right">{if $h.budget_amount}{call roas_money v=$h.budget_amount}{else}<span class="text-muted">—</span>{/if}</td>
								<td>{$h.primary_status|escape|lower}{if $h.primary_status_reasons} <span class="roas-sub">{$h.primary_status_reasons|escape|replace:'_':' '|lower}</span>{/if}</td>
							</tr>
						{/foreach}
					</tbody>
				</table>
				<p class="roas-sub">{tr}One row per change since the nightly settings snapshot began. Earlier days use the current settings.{/tr}</p>
			{else}
				<p class="text-muted">{tr}No settings history yet. The nightly pull records one snapshot per day.{/tr}</p>
			{/if}
		</div>
		<div class="col-md-7">
			<h3>{tr}What the network counted as value{/tr}</h3>
			{if $roasDetail.conversion_actions}
				<table class="table table-condensed roas-table">
					<thead>
						<tr>
							<th>{tr}Conversion action{/tr}</th>
							<th>{tr}Category{/tr}</th>
							<th class="text-right">{tr}Conversions{/tr}</th>
							<th class="text-right">{tr}Value (click){/tr}</th>
							<th class="text-right">{tr}Value (conv.){/tr}</th>
							<th class="text-right">{tr}All conv. value{/tr}</th>
						</tr>
					</thead>
					<tbody>
						{foreach from=$roasDetail.conversion_actions item=a}
							<tr>
								<td>{$a.conversion_action_name|escape}</td>
								<td>{$a.category|escape|replace:'_':' '|lower}</td>
								<td class="text-right">{call roas_num v=$a.conversions}</td>
								<td class="text-right">{call roas_money v=$a.conversions_value}</td>
								<td class="text-right">{call roas_money v=$a.value_by_conv_date}</td>
								<td class="text-right">{call roas_money v=$a.all_conversions_value}</td>
							</tr>
						{/foreach}
					</tbody>
				</table>
				<p class="roas-sub">{tr}Click-dated value is what the campaign table shows. Actions outside the purchase category still count when the network includes them in conversions.{/tr}</p>
			{else}
				<p class="text-muted">{tr}No conversion-action rows for this range. Run the pull with --conversions.{/tr}</p>
			{/if}
		</div>
	</div>

	<h3>{tr}Ad groups{/tr}</h3>
	{if $roasDetail.adgroups}
		<div class="table-responsive">
			<table class="table table-condensed table-striped roas-table roas-sortable">
				<thead>
					<tr>
						<th data-sortable="text">{tr}Ad group{/tr}</th>
						<th class="text-right grp-net" data-sortable="num">{tr}Spend{/tr}</th>
						<th class="text-right grp-net" data-sortable="num">{tr}Clicks{/tr}</th>
						<th class="text-right grp-net" data-sortable="num">{tr}Conversions{/tr}</th>
						<th class="text-right grp-net" data-sortable="num">{tr}Value (click){/tr}</th>
						<th class="text-right grp-net" data-sortable="num">{tr}ROAS{/tr}</th>
						<th class="text-right grp-books" data-sortable="num">{tr}Orders{/tr}</th>
						<th class="text-right grp-books" data-sortable="num">{tr}Revenue{/tr}</th>
						<th class="text-right grp-books" data-sortable="num">{tr}Commerce ROAS{/tr}</th>
					</tr>
				</thead>
				<tbody>
					{foreach from=$roasDetail.adgroups item=g}
						<tr>
							<td>{$g.adgroup_name|escape} <span class="roas-sub"><code>{$g.adgroup_id|escape}</code>{if $g.adgroup_kind eq 'asset_group'} {tr}asset group{/tr}{/if}{if $g.status && $g.status ne 'ENABLED'} · {$g.status|escape|lower}{/if}</span></td>
							<td class="text-right" data-sort="{$g.spend}">{call roas_money v=$g.spend}</td>
							<td class="text-right" data-sort="{$g.clicks}">{$g.clicks}</td>
							<td class="text-right" data-sort="{$g.network_conversions}">{call roas_num v=$g.network_conversions}</td>
							<td class="text-right" data-sort="{$g.network_value}">{call roas_money v=$g.network_value}</td>
							<td class="text-right" data-sort="{$g.network_roas}">{call roas_x v=$g.network_roas}</td>
							<td class="text-right" data-sort="{$g.orders}">{$g.orders}</td>
							<td class="text-right" data-sort="{$g.revenue}">{call roas_money v=$g.revenue}</td>
							<td class="text-right" data-sort="{$g.commerce_roas}">{call roas_x v=$g.commerce_roas}</td>
						</tr>
					{/foreach}
				</tbody>
				{if $roasDetail.adgroup_unassigned.orders}
					<tfoot>
						<tr>
							<th>{tr}Orders without an ad group{/tr}</th>
							<th colspan="5"></th>
							<th class="text-right">{$roasDetail.adgroup_unassigned.orders}</th>
							<th class="text-right">{call roas_money v=$roasDetail.adgroup_unassigned.revenue}</th>
							<th></th>
						</tr>
					</tfoot>
				{/if}
			</table>
		</div>
	{else}
		<p class="text-muted">{tr}No ad-group rows for this range. The nightly pull with --metrics=campaign,adgroup fills them.{/tr}</p>
	{/if}

	<h3>{tr}By day{/tr}</h3>
	<div class="table-responsive">
		<table class="table table-condensed roas-table">
			<thead>
				<tr>
					<th>{tr}Date{/tr}</th>
					<th class="text-right grp-net">{tr}Spend{/tr}</th>
					<th class="text-right grp-net">{tr}Clicks{/tr}</th>
					<th class="text-right grp-net">{tr}Impr.{/tr}</th>
					<th class="text-right grp-net">{tr}Conv.{/tr}</th>
					<th class="text-right grp-net">{tr}Value (click){/tr}</th>
					<th class="text-right grp-net">{tr}Value (conv.){/tr}</th>
					<th class="text-right grp-net">{tr}IS{/tr}</th>
					<th class="text-right grp-net">{tr}Budget lost{/tr}</th>
					<th class="text-right grp-books">{tr}Orders{/tr}</th>
					<th class="text-right grp-books">{tr}Revenue{/tr}</th>
				</tr>
			</thead>
			<tbody>
				{foreach from=$roasDetail.daily item=d}
					<tr>
						<td>{$d.metric_date|escape|truncate:10:""}</td>
						<td class="text-right">{call roas_money v=$d.spend}</td>
						<td class="text-right">{$d.clicks|default:0}</td>
						<td class="text-right">{$d.impressions|default:0}</td>
						<td class="text-right">{call roas_num v=$d.network_conversions}</td>
						<td class="text-right">{call roas_money v=$d.network_value}</td>
						<td class="text-right">{call roas_money v=$d.value_by_conv_date}</td>
						<td class="text-right">{call roas_pct v=$d.search_is}</td>
						<td class="text-right">{call roas_pct v=$d.search_budget_lost_is}</td>
						<td class="text-right">{$d.orders}</td>
						<td class="text-right">{call roas_money v=$d.revenue}</td>
					</tr>
				{/foreach}
			</tbody>
		</table>
	</div>

	<h3>{tr}Attributed orders{/tr} <small>({$roasDetail.orders|@count}{if $roasDetail.orders|@count >= 500}+{/if})</small></h3>
	{if $roasDetail.orders}
		<div class="table-responsive">
			<table class="table table-condensed table-striped roas-table">
				<thead>
					<tr>
						<th>{tr}Order{/tr}</th>
						<th>{tr}Purchased{/tr}</th>
						<th class="text-right">{tr}Total{/tr}</th>
						<th>{tr}Customer{/tr}</th>
						<th>{tr}First touch{/tr}</th>
						<th>{tr}Ad group / term{/tr}</th>
						<th>{tr}Counted in{/tr}</th>
					</tr>
				</thead>
				<tbody>
					{foreach from=$roasDetail.orders item=o}
						<tr>
							<td>{if $smarty.const.BITCOMMERCE_PKG_URL}<a href="{$smarty.const.BITCOMMERCE_PKG_URL}admin/orders.php?oID={$o.order_id|escape}&amp;action=edit">{$o.order_id|escape}</a>{else}{$o.order_id|escape}{/if}</td>
							<td>{$o.purchased_at|escape|truncate:16:""}</td>
							<td class="text-right">{call roas_money v=$o.revenue}</td>
							<td>{if $smarty.const.BITCOMMERCE_PKG_URL}<a href="{$smarty.const.BITCOMMERCE_PKG_URL}admin/list_orders.php?user_id={$o.user_id|escape}">{$o.user_id|escape}</a>{else}{$o.user_id|escape}{/if}</td>
							<td>{if $o.first_touch_at}{$o.first_touch_at|escape|truncate:10:""}{if $o.click_date} <span class="roas-sub">{tr}click{/tr}</span>{else} <span class="roas-sub">{tr}registration{/tr}</span>{/if}{else}<span class="text-muted">—</span>{/if}</td>
							<td class="roas-sub">{$o.adgroup_name|escape}{if $o.keyword_text} / {$o.keyword_text|escape}{/if}</td>
							<td class="roas-sub">{if $o.in_period && $o.in_period ne 'f'}{tr}period{/tr}{/if}{if $o.in_cohort && $o.in_cohort ne 'f'} {tr}cohort{/tr}{/if}</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		</div>
	{else}
		<p class="text-muted">{tr}No attributed orders in this range.{/tr}</p>
	{/if}
</div>
