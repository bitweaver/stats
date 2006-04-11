{strip}
<ul>
	{if $gBitUser->hasPermission( 'bit_p_view_site_stats' )}
		<li><a class="item" href="{$smarty.const.STATS_PKG_URL}index.php">{tr}Site Stats{/tr}</a></li>
	{/if}
	{if $gBitUser->hasPermission( 'p_stats_view' ) and $gBitSystem->mDb->mType eq 'postgres'}
		<li><a class="item" href="{$smarty.const.STATS_PKG_URL}users.php">{tr}User Stats{/tr}</a></li>
	{/if}
	{if $gBitSystem->isFeatureActive( 'referer_stats' ) and $gBitUser->hasPermission( 'p_stats_view_referer' )}
		<li><a class="item" href="{$smarty.const.STATS_PKG_URL}referer_stats.php">{tr}Referrer Stats{/tr}</a></li>
	{/if}
</ul>
{/strip}
