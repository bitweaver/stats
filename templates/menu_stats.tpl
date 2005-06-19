{strip}
<ul>
	{if $gBitUser->hasPermission( 'bit_p_view_site_stats' )}
		<li><a class="item" href="{$gBitLoc.STATS_PKG_URL}index.php">{tr}Site Stats{/tr}</a></li>
	{/if}{if $gBitUser->hasPermission( 'bit_p_view_ref_stats' )}
		<li><a class="item" href="{$gBitLoc.STATS_PKG_URL}referer_stats.php">{tr}Referrer Stats{/tr}</a></li>
	{/if}
</ul>
{/strip}
