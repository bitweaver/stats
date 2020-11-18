{* $Header$ *}
{strip}
{form legend="Statistics Settings"}
	<input type="hidden" name="page" value="{$page}" />

	<div class="form-group">
		{formlabel label="Google Analytics UA" for="analytics_google_ua"}
		{forminput}
			<input type="text" class="form-control" name="analytics_google_ua" id="analytics_google_ua" value="{$gBitSystem->getConfig('analytics_google_ua')|escape}" />
			{formhelp note="UA from anayltics.google.com"}
		{/forminput}
	</div>

	<div class="form-group">
		{formlabel label="Microsoft Analytics TI" for="analytics_microsoft_ti"}
		{forminput}
			<input type="text" class="form-control" name="analytics_microsoft_ti" id="analytics_microsoft_ti" value="{$gBitSystem->getConfig('analytics_microsoft_ti')|escape}" />
			{formhelp note="TI from ads.microsoft.com conversion javascript"}
		{/forminput}
	</div>

	{foreach from=$formFeaturesBit key=feature item=output}
		<div class="form-group">
			{formlabel label=$output.label for=$feature}
			{forminput label="checkbox"}
				{html_checkboxes name="$feature" values="y" checked=$gBitSystem->getConfig($feature) labels=false id=$feature}
				{formhelp note=$output.note page=$output.page}
			{/forminput}
		</div>
	{/foreach}

	<div class="form-group submit">
		<input type="submit" class="btn btn-default" name="change_prefs" value="{tr}Change preferences{/tr}" />
	</div>
{/form}
{/strip}
