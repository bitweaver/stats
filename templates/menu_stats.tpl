{strip}
<ul>
	{if $gBitUser->hasPermission( 'p_stats_view' )}
		<li><a class="item" href="{$smarty.const.STATS_PKG_URL}index.php">{biticon ipackage="icons" iname="go-home" iexplain="Site Stats" iforce="icon_text"}</a></li>
		{if $gBitSystem->mDb->mType eq 'postgres'}
			<li><a class="item" href="{$smarty.const.STATS_PKG_URL}users.php">{biticon ipackage="icons" iname="user-online" iexplain="User Stats" iforce="icon_text"}</a></li>
		{/if}
	{/if}
	{if $gBitSystem->isFeatureActive( 'stats_referers' ) and $gBitUser->hasPermission( 'p_stats_view_referer' )}
		<li><a class="item" href="{$smarty.const.STATS_PKG_URL}referer_stats.php">{biticon ipackage="icons" iname="go-last" iexplain="Referer Stats" iforce="icon_text"}</a></li>
	{/if}
</ul>
{/strip}
