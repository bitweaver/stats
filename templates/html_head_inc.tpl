{strip}
{* Google analytics setup *}
{if $gBitSystem->isTracking()}

	{* **** GOOGLE TAG MANAGER **** *}
	{if $gBitSystem->getConfig('google_tagmanager_id')}
{literal}
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0], j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src= 'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f); })(window,document,'script','dataLayer','{/literal}{$gBitSystem->getConfig('google_tagmanager_id')}{literal}');</script>
<!-- End Google Tag Manager -->
{/literal}
	{/if}

	{* **** GOOGLE ADWORDS **** *}
	{if $gBitSystem->getConfig('google_adwordstag_id')}
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$gBitSystem->getConfig('google_adwordstag_id')}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){ldelim}dataLayer.push(arguments);{rdelim}
  gtag('js', new Date());
  gtag('config', '{$gBitSystem->getConfig('google_adwordstag_id')}')
</script>
	{/if}

	{* **** GOOGLE UNIVERSAL ANALYTICS **** *}
<!-- Google Univeral Analytics -->
	{if $gBitSystem->getConfig('google_analytics_ua')}
<script async src="https://www.googletagmanager.com/gtag/js?id={$gBitSystem->getConfig('google_analytics_ua')}"></script>
<script>
	window.dataLayer = window.dataLayer || [];
	function gtag(){ldelim}dataLayer.push(arguments);{rdelim}
	gtag('js', new Date());
	gtag('config', '{$gBitSystem->getConfig('google_analytics_ua')}');
	{if $gBitUser->isRegistered()}gtag('set', {ldelim}'user_id': '{$gBitUser->mUserId}'{rdelim});{/if}
</script>
	{/if}

{/if}

{* **** MICROSOFT ANALYTICS **** *}
{if $gBitSystem->getConfig('microsoft_analytics_ti')}{literal}
<script>(function(w,d,t,r,u){var f,n,i;w[u]=w[u]||[],f=function(){var o={ti:"{/literal}{$gBitSystem->getConfig('microsoft_analytics_ti')}{literal}"};o.q=w[u],w[u]=new UET(o),w[u].push("pageLoad")},n=d.createElement(t),n.src=r,n.async=1,n.onload=n.onreadystatechange=function(){var s=this.readyState;s&&s!=="loaded"&&s!=="complete"||(f(),n.onload=n.onreadystatechange=null)},i=d.getElementsByTagName(t)[0],i.parentNode.insertBefore(n,i)})(window,document,"script","//bat.bing.com/bat.js","uetq");</script>
{/literal}{/if}

{/strip}
