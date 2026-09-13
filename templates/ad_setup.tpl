<div class="admin statistics ad-setup">
	<div class="header">
		<h1>{tr}Ad API setup{/tr}</h1>
	</div>
	<div class="body">
		{formfeedback hash=$feedback}

		<p class="help-block">
			{tr}These credentials let the warehouse pull spend. They are stored in site config, not in package source. Values already set are masked. Leave a field blank to keep the current value.{/tr}
		</p>

		{jstabs}
			{foreach from=$adsCatalog key=code item=net}
				{jstab title=$net.label}
					{if !$net.wired}
						<p class="text-muted">{tr}Not wired yet.{/tr}</p>
					{/if}

					<ol>
						{foreach from=$net.steps item=step}
							<li>{$step|escape}</li>
						{/foreach}
					</ol>

					{if $code eq 'microsoft'}
						<p>
							<strong>{tr}Redirect URI{/tr}</strong>
							— {tr}paste this exact URL into the Azure app registration (Web redirect URI) before Sign in:{/tr}
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
							<p class="help-block">{tr}Save Azure client id and client secret first, then Sign in appears here.{/tr}</p>
						{/if}
					{/if}

					{if $net.keys}
						<form method="post" action="{$smarty.const.STATS_PKG_URL}ad_setup.php">
							<input type="hidden" name="tk" value="{$gBitUser->mTicket|escape}" />
							<table class="table table-condensed">
								<thead>
									<tr>
										<th>{tr}Field{/tr}</th>
										<th>{tr}Status{/tr}</th>
										<th>{tr}New value{/tr}</th>
									</tr>
								</thead>
								<tbody>
									{foreach from=$net.keys key=key item=row}
										<tr>
											<td>
												{$row.label|escape}
												<div class="help-block"><code>{$key|escape}</code> {$row.hint|escape}</div>
											</td>
											<td>{if $row.set}<span class="text-success">{$row.mask|escape}</span>{else}<span class="text-muted">{tr}missing{/tr}</span>{/if}</td>
											<td>
												{if $row.type eq 'textarea'}
													<textarea class="form-control" name="ads_secret[{$key|escape}]" rows="6" autocomplete="off" placeholder="{if $row.set}{tr}unchanged{/tr}{else}{tr}paste JSON here{/tr}{/if}"></textarea>
												{else}
													<input class="form-control" type="password" name="ads_secret[{$key|escape}]" value="" autocomplete="off" placeholder="{if $row.set}{tr}unchanged{/tr}{else}{tr}paste here{/tr}{/if}" />
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
