<script>
    function priceSorter(a, b) {
		if( a ) {
	        a = parseFloat( a.replace(/[^\d.]/g,'') );
		}
		if( b ) {
	        b = parseFloat( b.replace(/[^\d.]/g,'') );
		}
		if (a > b) return 1;
		if (a < b) return -1;
        return 0;
    }
</script>

<div class="floaticon">{bithelp}</div>

<div class="display statistics">
	<div class="header">
		<h1>{tr}User Registration Statistics{/tr} {$smarty.request.timeframe|escape}</h1>
		{minifind period_format="`$smarty.request.period_format`" timeframe="`$smarty.request.timeframe`"}
	</div>



	<div class="body">
		<table class="table data">
			<caption>{tr}User Registration Statistics{/tr}</caption>
			{assign var=refCount value=0}
			{foreach from=$referers key=host item=reg}
				{assign var=hostKey value=$host|strip:'.':''}
				{assign var=regCount value=$reg|@count}
				{assign var=totalReg value=$totalReg+$regCount}
				{assign var=hostHash value=$host|md5}	
				<tr>
					<th>{booticon iname='icon-search' class="btn btn-default btn-sm" onclick="BitBase.toggleElementDisplay('`$hostHash`','table-row-group');"}</th>
					<th style="position:relative"><div style="position:absolute;z-index:-1;width:{math equation="round( ( r / m ) * 100 )" r=$reg|@count m=$maxRegistrations}%; background:#CAF3FF;padding:0 0 0 5px;">&nbsp;</div>{$host|escape}</th>
					<th class="text-right"><div class="floaticon"> [{math equation="round((x / y) * 100)" x=$reg|@count y=$totalRegistrations}% ] <a href="{$smarty.server.SCRIPT_NAME}?period={$smarty.request.period}&amp;find={$host|escape}">{booticon iname='icon-clock'}</a></div></th>
				{if $aggregateStats.$host}
					<th class="text-right">{$reg|@count} {booticon iname="icon-user"}</th>
					<th class="text-right">{$aggregateStats.$host.orders|default:"0"} {booticon iname="icon-shopping-cart"}</th>
					<th class="text-right">{$gCommerceCurrencies->format($aggregateStats.$host.revenue|default:"0.00")}</th>
				{/if}
				</tr>

				<tbody id="{$hostHash}" style="display:none">
					<tr><td colspan="6">
						<div class="panel-group" id="accordion-{$hostHash}" role="tablist" aria-multiselectable="true">
						{foreach from=$aggregateStats.$host.values item=paramValues key=paramKey name=agstat}
							<div class="panel panel-default">
								<div class="panel-heading" role="tab" id="accordion-{$paramKey}">
									<div class="pull-right">{$paramValues.registrations} {booticon iname="icon-user"} {$paramValues.orders} {booticon iname="icon-shopping-cart"} {$gCommerceCurrencies->format($paramValues.revenue)}</div>
									<h4 class="panel-title">
										<a class="collapsed" data-toggle="collapse" data-parent="#accordion-{$hostHash}" href="#collapse-{$hostHash}-{$paramKey}" aria-expanded="false" aria-controls="collapse-{$hostHash}-{$paramKey}">{$paramKey}</a>
									</h4>
								</div>
{if $paramValues.values}
								<div id="collapse-{$hostHash}-{$paramKey}" class="panel-collapse collapse" role="tabpanel" aria-labelledby="heading{$smarty.foreach.agstat.iter}">
									<div class="panel-body">
										<div class="panel-group" id="accordion-{$hostHash}-{$paramKey}" role="tablist" aria-multiselectable="true">
<table data-toggle="table">
<thead>
<tr>
	<th>{$paramKey}</th>
	<th class="text-center" data-field="registrations" data-sortable="true">{booticon iname="icon-user"}</th>
	<th class="text-center" data-field="order_count" data-sortable="true">{booticon iname="icon-shopping-cart"}</th>
	<th class="text-center" data-field="revenue" data-sortable="true" data-sorter="priceSorter">Revenue</th>
</tr>
</thead>
<tbody>
										{foreach from=$paramValues.values item=valueHash key=valueKey name=paramval}
<tr>
	<td><a href="#" onclick="BitBase.toggleElementDisplay('order-list-{$hostHash}-{$paramKey}-{$valueKey}','block');return false;">{$valueKey|default:"unknown"}</a>
		<ol class="data" id="order-list-{$hostHash}-{$paramKey}-{$valueKey}" style="display:none">
		{foreach from=$valueHash.users item=userHash}
			<li class="item width100p" style="border-bottom:1px solid #cccccc;">
				<div class="inline-block width10p date">{$userHash.registration_date|bit_date_format}</div>
				<div class="inline-block width65p">{if $userHash.referer_url}<a href="{$userHash.referer_url|escape}">{$userHash.referer_url|stats_referer_display_short}</a><br/>{/if}{BitUser::getDisplayLink(1,$userHash)} - {$userHash.email}</div>
				<div class="inline-block text-right width10p">{if $userHash.revenue.total_orders}<a target="_new" href="{$smarty.const.BITCOMMERCE_PKG_URL}admin/list_orders.php?user_id={$userHash.user_id}">{$userHash.revenue.total_orders} {tr}orders{/tr}</a>{/if}</div>
				<div class="inline-block text-right width10p">{if $userHash.revenue.total_revenue}{$gCommerceCurrencies->format($userHash.revenue.total_revenue)}{/if}</div>
			</li>
		{/foreach}
		</ol>
	</td>
	<td class="text-right">{if $valueHash.users}{$valueHash.users|count}{/if}</td>
	<td class="text-right">{if $valueHash.orders}{$valueHash.orders}{/if}</td>
	<td class="text-right">{if $valueHash.revenue}{$gCommerceCurrencies->format($valueHash.revenue)}{/if}</td>
</tr>
										{/foreach}
</tbody>
</table>
										</div>
									</div>
								</div>
{/if}
							</div>
						{/foreach}
							{assign var=paramKey value="Everything"}
							<div class="panel panel-default">
								<div class="panel-heading" role="tab" id="accordion-{$paramKey}">
								{if $aggregateStats.$host}
									<div class="pull-right">
										{$reg|@count} {booticon iname="icon-user"} {$aggregateStats.$host.orders|default:"0"} {booticon iname="icon-shopping-cart"} {$gCommerceCurrencies->format($aggregateStats.$host.revenue|default:"0.00")}
									</div>
								{/if}
									<h4 class="panel-title">
										<a class="collapsed" data-toggle="collapse" data-parent="#accordion-{$hostHash}" href="#collapse-{$hostHash}-{$paramKey}" aria-expanded="false" aria-controls="collapse-{$hostHash}-{$paramKey}">{$paramKey}</a>
									</h4>
								</div>
								{if $reg}
								<div id="collapse-{$hostHash}-{$paramKey}" class="panel-collapse collapse" role="tabpanel" aria-labelledby="heading{$smarty.foreach.agstat.iter}">
									<div class="panel-body">
										<div class="panel-group" id="accordion-{$hostHash}-{$paramKey}" role="tablist" aria-multiselectable="true">
											<table data-toggle="table">
											<thead>
											<tr>
												<th></th>
												<th data-field="name" data-sortable="true">User</th>
												<th class="text-center" data-field="order_count" data-sortable="true">{booticon iname="icon-shopping-cart"}</th>
												<th class="text-center" data-field="revenue" data-sortable="true" data-sorter="priceSorter">Revenue</th>
											</tr>
											</thead>
											<tbody>
											{foreach from=$reg key=userId item=user}
											<tr class="{cycle values='odd,even'}">
												<td class="date">{$user.registration_date|bit_date_format}</td>
												<td><strong style="font-size:larger">{displayname hash=$user}</strong>{if $user.referer_url}<br/><a href="{$user.referer_url|escape}">{$user.referer_url|stats_referer_display_short}</a>{/if}</td>
												<td class="text-right">{if $user.revenue.total_orders}{$user.revenue.total_orders}{/if}</td>
												<td class="text-right">{if $user.revenue.total_revenue}{$gCommerceCurrencies->format($user.revenue.total_revenue)}{/if}</td>
											</tr>
											{/foreach}
											</tbody>
											</table>
										</div>
									</div>
								</div>
								{/if}
							</div>
						</div>
					</td></tr>
				</tbody>

			{foreachelse}
				<tr class="norecords"><td colspan="3">{tr}No records found{/tr}</td></tr>
			{/foreach}
				<tr>
					<th style="width:5%;">{$totalReg}</th>
					<th>{tr}Total Registrations{/tr}</th>
				</tr>
		</table>
	</div> <!-- end .body -->

	{include file="bitpackage:kernel/pagination.tpl"}
</div> <!-- end .statistics -->
