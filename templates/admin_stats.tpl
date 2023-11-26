{* $Header$ *}
{strip}
{form legend="Statistics Settings"}
	<input type="hidden" name="page" value="{$page}" />

	{foreach from=$formFeaturesBit key=feature item=output}
		<div class="form-group">
			{if $output.type == 'text'}
				{forminput}
					{formlabel label=$output.label for=$feature}
					<input type="text" class="form-control" name="{$feature}" id="{$feature}" value="{$gBitSystem->getConfig($feature)|escape}" />
					{formhelp note=$output.note page=$output.page link=$output.link}
				{/forminput}
			{else}
				{forminput label="checkbox"}
					{html_checkboxes name="$feature" values="y" checked=$gBitSystem->getConfig($feature) labels=false id=$feature} {tr}{$output.label}{/tr}
					{formhelp note=$output.note page=$output.page link=$output.link}
				{/forminput}
			{/if}
		</div>
	{/foreach}

	<div class="form-group submit">
		<input type="submit" class="btn btn-default" name="change_prefs" value="{tr}Change preferences{/tr}" />
	</div>
{/form}
{/strip}
