{* $Header: /cvsroot/bitweaver/_bit_stats/templates/admin_stats.tpl,v 1.1 2006/09/10 17:43:28 squareing Exp $ *}
{strip}
{form legend="Statistics Settings"}
	<input type="hidden" name="page" value="{$page}" />
	{foreach from=$formFeaturesBit key=feature item=output}
		<div class="row">
			{formlabel label=`$output.label` for=$feature}
			{forminput}
				{html_checkboxes name="$feature" values="y" checked=$gBitSystem->getConfig($feature) labels=false id=$feature}
				{formhelp note=`$output.note` page=`$output.page`}
			{/forminput}
		</div>
	{/foreach}

	<div class="row submit">
		<input type="submit" name="change_prefs" value="{tr}Change preferences{/tr}" />
	</div>
{/form}
{/strip}
