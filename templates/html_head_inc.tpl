{strip}
{* Google analytics setup *}
{if $gBitSystem->isTracking()}

	{* **** GOOGLE ANALYTICS **** *}
	{if $gBitSystem->getConfig('analytics_google_ua')}
<script async src="https://www.googletagmanager.com/gtag/js?id={$gBitSystem->getConfig('analytics_google_ua')}"></script>
<script>
	window.dataLayer = window.dataLayer || [];
	function gtag(){ldelim}dataLayer.push(arguments);{rdelim}
	gtag('js', new Date());
	gtag('config', '{$gBitSystem->getConfig('analytics_google_ua')}');
	{if $gBitUser->isRegistered()}gtag('set', {ldelim}'user_id': '{$gBitUser->mUserId}'{rdelim});{/if}
</script>
	{/if}

	{* **** MICROSOFT ANALYTICS **** *}
	{if $gBitSystem->getConfig('analytics_microsoft_ti')}{literal}
<script>(function(w,d,t,r,u){var f,n,i;w[u]=w[u]||[],f=function(){var o={ti:"{/literal}{$gBitSystem->getConfig('analytics_microsoft_ti')}{literal}"};o.q=w[u],w[u]=new UET(o),w[u].push("pageLoad")},n=d.createElement(t),n.src=r,n.async=1,n.onload=n.onreadystatechange=function(){var s=this.readyState;s&&s!=="loaded"&&s!=="complete"||(f(),n.onload=n.onreadystatechange=null)},i=d.getElementsByTagName(t)[0],i.parentNode.insertBefore(n,i)})(window,document,"script","//bat.bing.com/bat.js","uetq");</script>
<noscript><img src="//bat.bing.com/action/0?ti={/literal}{$gBitSystem->getConfig('analytics_microsoft_ti')}{literal}&Ver=2" height="0" width="0" style="display:none; visibility: hidden;" /></noscript>
	{/literal}{/if}

{/if}
{/strip}
