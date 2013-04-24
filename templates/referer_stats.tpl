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
				{assign var=regCount value=$reg|@count}
				{assign var=totalReg value=$totalReg+$regCount}
				{assign var=hostHash value=$host|md5}	
				<tr>
					<th style="width:5%;">{$reg|@count}</th>
					<th><div style="width:{math equation="round( ( r / m ) * 100 )" r=$reg|@count m=$maxRegistrations}%; background:#f80;padding:0 0 0 5px;">{$host|escape}</div></th>
					<th><div class="floaticon"> [{math equation="round((x / y) * 100)" x=$reg|@count y=$totalRegistrations}% ] <a href="{$smarty.server.SCRIPT_NAME}?period={$smarty.request.period}&amp;find={$host|escape}">{booticon iname='icon-clock'}</a>{biticon iname='folder-saved-search' onclick="BitBase.toggleElementDisplay('`$hostHash`','table-row-group');"}</div></th>
				</tr>

				<tbody id="{$hostHash}" style="display:none">
				{foreach from=$reg key=userId item=user}
					<tr class="{cycle values='odd,even'}">
						<td colspan="2">
							<strong style="font-size:larger">{displayname hash=$user}</strong>{if $user.referer_url}<br/><a href="{$user.referer_url|escape}">{$user.referer_url|stats_referer_display_short}</a>{/if}
						</td>
						<td class="date">{$user.registration_date|bit_date_format}</td>
					</tr>
				{/foreach}
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
