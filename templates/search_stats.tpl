<div class="floaticon">{bithelp}</div>

<div class="display statistics">
<div class="header">
<h1><a href="{$smarty.const.SEARCH_PKG_URL}stats.php">{tr}Search stats{/tr}</a></h1>
</div>

<div class="body">

<table class="find">
<tr><td>{tr}Find{/tr}</td>
   <td>
   <form method="get" action="{$smarty.const.SEARCH_PKG_URL}stats.php">
     <input type="text" name="find" value="{$find|escape}" />
     <input type="submit" value="{tr}find{/tr}" name="search" />
     <input type="hidden" name="sort_mode" value="{$sort_mode|escape}" />
   </form>
   </td>
</tr>
</table>

<table class="data">
<tr>
<th><a href="{$smarty.const.SEARCH_PKG_URL}stats.php?offset={$offset}&amp;sort_mode={if $sort_mode eq 'term_desc'}term_asc{else}term_desc{/if}">{tr}Term{/tr}</a></th>
<th><a href="{$smarty.const.SEARCH_PKG_URL}stats.php?offset={$offset}&amp;sort_mode={if $sort_mode eq 'hits_desc'}hits_asc{else}hits_desc{/if}">{tr}Searched{/tr}</a></th>
</tr>
{cycle values="even,odd" print=false}
{section name=user loop=$channels}
<tr class="{cycle}">
<td>{$channels[user].term}</td>
<td>{$channels[user].hits}</td>
</tr>
{sectionelse}
<tr class="norecords"><td colspan="2">{tr}No records found{/tr}</td></tr>
{/section}
</table>

</div> <!-- end .body -->

<div class="navbar">
	<a div href="{$smarty.const.SEARCH_PKG_URL}stats.php?clear=1">{tr}clear stats{/tr}</a>
</div>

{include file="bitpackage:kernel/pagination.tpl"}

</div> <!-- end .statistics -->
