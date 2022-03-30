{assign var="titleHash" value="`$parentHash``$tableHash.info.title`"|md5}
<tbody style="{if $depth>1}display:none;{/if}" class="group-body-{$parentHash} depth-{$depth}">
<tr>
	<th style="padding-left:{$depth|default:1}em;"><a href="#" onclick="$('.group-body-{$titleHash}.depth-{$depth+1}').toggle();return false;">{$tableHash.info.title|default:"unknown"}</a></th>
	<th class="text-right width10p"><a href="#" onclick="BitBase.toggleElementDisplay('user-list-{$titleHash}','block');return false;">{$tableHash.info.users|count|default:"0"} {booticon iname="icon-user"}</a></th>
	<th class="text-right width10p">{$tableHash.info.orders|default:"0"} {booticon iname="icon-shopping-cart"}</th>
	<th class="text-right width10p">{$gCommerceCurrencies->format($tableHash.info.revenue)}</th>
</tr>
<tr>
	<td colspan="4" id="user-list-{$titleHash}" style="display:none">
		<ol class="data" id="user-list-{$hostHash}-{$paramKey}-{$valueKey}">
		{foreach from=$tableHash.info.users item=userHash}
			<li class="item width100p" style="border-bottom:1px solid #cccccc;">
				<div class="inline-block width10p date">{$userHash.registration_date|bit_date_format}</div>
				<div class="inline-block width65p">{if $userHash.referer_url}<a href="{$userHash.referer_url|escape}">{$userHash.referer_url|stats_referer_display_short}</a><br/>{/if}{BitUser::getDisplayLinkFromHash($userHash,1)} - {$userHash.email}</div>
				<div class="inline-block text-right width10p">{if $userHash.revenue.total_orders}<a target="_new" href="{$smarty.const.BITCOMMERCE_PKG_URL}admin/list_orders.php?user_id={$userHash.user_id}">{$userHash.revenue.total_orders} {tr}orders{/tr}</a>{/if}</div>
				<div class="inline-block text-right width10p">{if $userHash.revenue.total_revenue}{$gCommerceCurrencies->format($userHash.revenue.total_revenue)}{/if}</div>
			</li>
		{/foreach}
		</ol>
	</td>
</tr>
</tbody>
{if $tableHash.values}
	{foreach from=$tableHash.values item=subHash}
		{include file="bitpackage:stats/referrer_stats_ctm_inc.tpl" tableHash=$subHash depth=$depth+1 parentHash=$titleHash}
	{/foreach}
{/if}
