{strip}
<ul>
	{if $gBitUser->hasPermission( 'p_stats_view' )}
		<li><a class="item" href="{$smarty.const.STATS_PKG_URL}index.php">{booticon iname="icon-home" iexplain="Site Stats" ilocation=menu}</a></li>
		{if $gBitSystem->mDb->mType eq 'postgres'}
			<li><a class="item" href="{$smarty.const.STATS_PKG_URL}users.php">{booticon iname="icon-user" iexplain="User Stats" ilocation=menu}</a></li>
		{/if}
	{/if}
	{if $gBitSystem->isFeatureActive( 'stats_referers' ) and $gBitUser->hasPermission( 'p_stats_view_referer' )}
		<li><a class="item" href="{$smarty.const.STATS_PKG_URL}referer_stats.php">{booticon iname="icon-bullhorn" iexplain="Referer Stats" ilocation=menu}</a></li>
	{/if}
</ul>
{/strip}
