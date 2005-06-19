<div class="floaticon">{bithelp}</div>

<div class="display statistics">
<div class="header">
<h1><a href="{$gBitLoc.STATS_PKG_URL}referer_stats.php">{tr}Referer stats{/tr}</a></h1>
</div>

<div class="body">

<table class="find">
<tr><td>{tr}Find{/tr}</td>
   <td>
   <form method="get" action="{$gBitLoc.STATS_PKG_URL}referer_stats.php">
     <input type="text" name="find" value="{$find|escape}" />
     <input type="submit" value="{tr}find{/tr}" name="referer" />
     <input type="hidden" name="sort_mode" value="{$sort_mode|escape}" />
   </form>
   </td>
</tr>
</table>

<table class="data">
<tr>
<th><a href="{$gBitLoc.STATS_PKG_URL}referer_stats.php?offset={$offset}&amp;sort_mode={if $sort_mode eq 'referer_desc'}referer_asc{else}referer_desc{/if}">{tr}Term{/tr}</a></th>
<th><a href="{$gBitLoc.STATS_PKG_URL}referer_stats.php?offset={$offset}&amp;sort_mode={if $sort_mode eq 'hits_desc'}hits_asc{else}hits_desc{/if}">{tr}Hits{/tr}</a></th>
<th><a href="{$gBitLoc.STATS_PKG_URL}referer_stats.php?offset={$offset}&amp;sort_mode={if $sort_mode eq 'last_desc'}last_asc{else}last_desc{/if}">{tr}Last{/tr}</a></th>
</tr>
{cycle values="even,odd" print=false}
{section name=user loop=$channels}
<tr class="{cycle}">
<td>{$channels[user].referer}</td>
<td>{$channels[user].hits}</td>
<td>{$channels[user].last|bit_short_datetime}</td>
</tr>
{sectionelse}
<tr class="norecords"><td colspan="2">{tr}No records found{/tr}</td></tr>
{/section}
</table>

</div> <!-- end .body -->

<div class="navbar">
	<a href="{$gBitLoc.STATS_PKG_URL}referer_stats.php?clear=1">{tr}clear stats{/tr}</a>
</div>

{include file="bitpackage:kernel/pagination.tpl"}

</div> <!-- end .statistics -->
