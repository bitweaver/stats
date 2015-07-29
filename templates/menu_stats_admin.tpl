{strip}
{if $packageMenuTitle}<a href="#"> {tr}{$packageMenuTitle|capitalize}{/tr}</a>{/if}
<ul class="{$packageMenuClass}">
	<li><a class="item" href="{$smarty.const.KERNEL_PKG_URL}admin/index.php?page=stats">{tr}Stats Settings{/tr}</a></li>
	<li><a class="item" href="{$smarty.const.STATS_PKG_URL}index.php">{tr}Site Stats{/tr}</a></li>
{if $gBitSystem->mDb->mType eq 'postgres'}
	<li><a class="item" href="{$smarty.const.STATS_PKG_URL}users.php">{tr}User Stats{/tr}</a></li>
{/if}
{if $gBitSystem->isFeatureActive( 'stats_referers' ) and $gBitUser->hasPermission( 'p_stats_view_referer' )}
	<li><a class="item" href="{$smarty.const.STATS_PKG_URL}referrers.php">{tr}Referer Stats{/tr}</a></li>
{/if}
</ul>
{/strip}
