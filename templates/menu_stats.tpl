{strip}
<ul>
	{if $gBitUser->hasPermission( 'bit_p_view_site_stats' )}
		<li><a class="item" href="{$smarty.const.STATS_PKG_URL}index.php">{tr}Site Stats{/tr}</a></li>
	{/if}
	{if $gBitUser->hasPermission( 'bit_p_view_stats' ) and $gBitSystem->mDb->mType eq 'postgres'}
		<li><a class="item" href="{$smarty.const.STATS_PKG_URL}users.php">{tr}User Stats{/tr}</a></li>
	{/if}
	{if $gBitSystem->isFeatureActive( 'feature_referer_stats' ) and $gBitUser->hasPermission( 'bit_p_view_referer_stats' )}
		<li><a class="item" href="{$smarty.const.STATS_PKG_URL}referer_stats.php">{tr}Referrer Stats{/tr}</a></li>
	{/if}
</ul>
{/strip}
