{strip}
<style type="text/css">
.ad-setup .ads-trunc { cursor: pointer; color: #333; }
.ad-setup .ads-trunc:hover { text-decoration: underline; }
.ad-setup .ads-edit { display: none; }
.ad-setup .ads-edit.is-open { display: block; }
.ad-setup td.ads-field { padding-right: 0.5em; vertical-align: middle; }
.ad-setup .ads-keywrap { margin-left: 0.5em; line-height: 1.4; }
.ad-setup .ads-key { color: #999; text-decoration: none; }
.ad-setup .ads-key:hover { color: #555; }
.ad-setup .ads-keyname { display: none; cursor: pointer; font-size: 12px; }
.ad-setup .ads-keyname.is-open { display: inline; }
.ad-setup td.ads-value { min-width: 16em; vertical-align: middle; }
</style>
{/strip}
<div class="admin statistics ad-setup">
	<div class="header">
		<h1>{tr}Ad API setup{/tr}</h1>
	</div>
	<div class="body">
		{formfeedback hash=$feedback}

		<p class="help-block">
			{tr}These credentials let the warehouse pull spend. Click a stored value to edit it. Empty fields are for values not entered yet.{/tr}
		</p>

		{jstabs}
			{foreach from=$adsCatalog key=code item=net}
				{jstab title=$net.label}
					{if !$net.wired}
						<p class="text-muted">{tr}Not wired yet.{/tr}</p>
					{/if}

					<p>
						<a href="#" onclick="BitBase.toggleElementDisplay('ads-help-{$code}','block');return false;">
							{booticon iname="fa-circle-question"} {tr}Setup instructions{/tr}
						</a>
					</p>
					<div class="box" id="ads-help-{$code}" style="display:none">
						<h2>{tr}Setup instructions{/tr}</h2>
						<ol>
							{foreach from=$net.steps item=step}
								<li>{$step}</li>
							{/foreach}
						</ol>
					</div>

					{if $code eq 'microsoft'}
						<p>
							<strong>{tr}Redirect URI{/tr}</strong>
							— {tr}paste this exact URL into the{/tr}
							<a href="https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank" rel="noopener noreferrer">{tr}Azure app registration{/tr}</a>
							{tr}(Web redirect URI) before Sign in:{/tr}
							<code>{$adsRedirectUri|escape}</code>
						</p>
						{if $adsMsAuthorize}
							<p>
								<a class="btn btn-default" href="{$adsMsAuthorize|escape}">
									{tr}Sign in to Microsoft Advertising{/tr}
								</a>
								{tr}(saves the refresh token){/tr}
							</p>
						{else}
							<p class="help-block">
								{tr}Save{/tr}
								<a href="https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank" rel="noopener noreferrer">{tr}Azure{/tr}</a>
								{tr}client id and client secret first, then Sign in appears here.{/tr}
							</p>
						{/if}
					{/if}

					{if $net.keys}
						<form method="post" action="{$smarty.const.STATS_PKG_URL}admin/ad_setup.php">
							<input type="hidden" name="tk" value="{$gBitUser->mTicket|escape}" />
							<table class="table table-condensed">
								<thead>
									<tr>
										<th>{tr}Field{/tr}</th>
										<th>{tr}Value{/tr}</th>
									</tr>
								</thead>
								<tbody>
									{foreach from=$net.keys key=key item=row}
										<tr>
											<td class="ads-field">
												<span class="pull-right ads-keywrap">
													<a class="ads-key" href="#" title="{$key|escape}" onclick="this.style.display='none'; this.nextSibling.className='ads-keyname is-open'; return false;">{booticon iname="fa-code" iexplain=$key}</a><code class="ads-keyname" title="{tr}Click to copy{/tr}" onclick="var t=this.textContent; if(navigator.clipboard) navigator.clipboard.writeText(t); else { var r=document.createRange(); r.selectNodeContents(this); var s=window.getSelection(); s.removeAllRanges(); s.addRange(r); document.execCommand('copy'); } return false;">{$key|escape}</code>
												</span>
												{$row.label|escape}
												{if $row.hint}<div class="help-block">{$row.hint|escape}</div>{/if}
											</td>
											<td class="ads-value">
												{if $row.set}
													<span class="ads-trunc" onclick="var c=this.parentNode; this.style.display='none'; c.querySelector('.ads-edit').className='ads-edit is-open'; var i=c.querySelector('input,textarea'); if(i){ i.disabled=false; i.focus(); }">{$row.display|escape}</span>
													<div class="ads-edit">
														{if $row.type eq 'textarea'}
															<textarea class="form-control" name="ads_secret[{$key|escape}]" rows="8" autocomplete="off" disabled="disabled">{$row.value|escape}</textarea>
														{else}
															<input class="form-control" type="text" name="ads_secret[{$key|escape}]" value="{$row.value|escape}" autocomplete="off" disabled="disabled" />
														{/if}
													</div>
												{else}
													{if $row.type eq 'textarea'}
														<textarea class="form-control" name="ads_secret[{$key|escape}]" rows="6" autocomplete="off" placeholder="{tr}paste JSON here{/tr}"></textarea>
													{else}
														<input class="form-control" type="text" name="ads_secret[{$key|escape}]" value="" autocomplete="off" />
													{/if}
												{/if}
											</td>
										</tr>
									{/foreach}
								</tbody>
							</table>
							<div class="form-group submit">
								<button type="submit" class="btn btn-default" name="save_ads_secrets" value="1">{tr}Save{/tr} {$net.label|escape}</button>
							</div>
						</form>
					{/if}
				{/jstab}
			{/foreach}
		{/jstabs}
	</div>
</div>
