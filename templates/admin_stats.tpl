{* $Header$ *}
{strip}
{form legend="Statistics Settings"}
	<input type="hidden" name="page" value="{$page}" />
	{foreach from=$formFeaturesBit key=feature item=output}
		<div class="control-group">
			{formlabel label=$output.label for=$feature}
			{forminput}
				{html_checkboxes name="$feature" values="y" checked=$gBitSystem->getConfig($feature) labels=false id=$feature}
				{formhelp note=$output.note page=$output.page}
			{/forminput}
		</div>
	{/foreach}

	<div class="control-group submit">
		<input type="submit" class="btn btn-default" name="change_prefs" value="{tr}Change preferences{/tr}" />
	</div>
{/form}
{/strip}
