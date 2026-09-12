<div class="admin statistics ad-setup">
	<div class="header">
		<h1>{tr}Ad API setup{/tr}</h1>
	</div>
	<div class="body">
		{formfeedback hash=$feedback}

		<p class="help-block">
			{tr}These credentials let the warehouse pull spend. They are stored in site config, not in package source. Values already set are masked. Leave a field blank to keep the current value.{/tr}
		</p>

		<p>
			<strong>{tr}Microsoft redirect URI{/tr}</strong>
			— {tr}paste this exact URL into the Azure app registration (Web redirect URI) before Sign in:{/tr}
			<code>{$adsRedirectUri|escape}</code>
		</p>

		{foreach from=$adsCatalog key=code item=net}
			<h2>{$net.label|escape}</h2>
			{if !$net.wired}
				<p class="text-muted">{tr}Not wired yet.{/tr}</p>
			{/if}
			<ol>
				{foreach from=$net.steps item=step}
					<li>{$step|escape}</li>
				{/foreach}
			</ol>
			{if $code eq 'microsoft' && $adsMsAuthorize}
				<p>
					<a class="btn btn-default" href="{$adsMsAuthorize|escape}">
						{tr}Sign in to Microsoft Advertising{/tr}
					</a>
					{tr}(saves the refresh token){/tr}
				</p>
			{elseif $code eq 'microsoft'}
				<p class="help-block">{tr}Save Azure client id and client secret first, then Sign in appears here.{/tr}</p>
			{/if}
		{/foreach}

		<form method="post" action="{$smarty.const.STATS_PKG_URL}ad_setup.php">
			<input type="hidden" name="tk" value="{$gBitUser->mTicket|escape}" />
			<table class="table table-condensed">
				<thead>
					<tr>
						<th>{tr}Network{/tr}</th>
						<th>{tr}Field{/tr}</th>
						<th>{tr}Status{/tr}</th>
						<th>{tr}New value{/tr}</th>
					</tr>
				</thead>
				<tbody>
					{foreach from=$adsStatus item=row}
						<tr>
							<td>{$row.network_label|escape}</td>
							<td>
								{$row.label|escape}
								<div class="help-block"><code>{$row.key|escape}</code> {$row.hint|escape}</div>
							</td>
							<td>{if $row.set}<span class="text-success">{$row.mask|escape}</span>{else}<span class="text-muted">{tr}missing{/tr}</span>{/if}</td>
							<td>
								<input class="form-control" type="password" name="ads_secret[{$row.key|escape}]" value="" autocomplete="off" placeholder="{if $row.set}{tr}unchanged{/tr}{else}{tr}paste here{/tr}{/if}" />
							</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
			<button type="submit" class="btn btn-default" name="save_ads_secrets" value="1">{tr}Save values{/tr}</button>
		</form>
	</div>
</div>
