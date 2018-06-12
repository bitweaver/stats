{strip}
{* Google analytics setup *}
{if $gBitSystem->isTracking()}

	{* **** GOOGLE ANALYTICS **** *}
	{if $gBitSystem->getConfig('google_analytics_ua')}
<script async src="https://www.googletagmanager.com/gtag/js?id={$gBitSystem->getConfig('google_analytics_ua')}"></script>
<script>
	window.dataLayer = window.dataLayer || [];
	function gtag(){ldelim}dataLayer.push(arguments);{rdelim}
	gtag('js', new Date());
	gtag('config', '{$gBitSystem->getConfig('google_analytics_ua')}');
	{if $gBitUser->isRegistered()}gtag('set', {ldelim}'user_id': '{$gBitUser->mUserId}'{rdelim});{/if}
</script>

{*<script>
	{literal}(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)})(window,document,'script','//www.google-analytics.com/analytics.js','ga');{/literal}
	ga('create', '{$gBitSystem->getConfig('google_analytics_ua')}', 'auto');
	{if $gBitUser->isRegistered()}ga('set', 'userId', '{$gBitUser->mUserId}');{/if}
	ga('require', 'displayfeatures');
	ga('require', 'linkid', 'linkid.js');
{if $gBitSystem->isPackageActive('bitcommerce')}
	ga('require', 'ec');
{/if}
	ga('send', 'pageview');
</script>*}
	{/if}

	{* **** BOOSTSUITE **** *}
	{*if $gBitSystem->getConfig('boostsuite_site_id')}
<script type="text/javascript">
{literal}
var _bsc = _bsc || {"suffix":""}; 
(function() {
	var bs = document.createElement('script');
	bs.type = 'text/javascript';
	bs.async = true;
	bs.src = ('https:' == document.location.protocol ? 'https' : 'http') + '://d2so4705rl485y.cloudfront.net/widgets/tracker/tracker.js';
	var s = document.getElementsByTagName('script')[0];
	s.parentNode.insertBefore(bs, s); 
})();
{/literal}
</script>
	{/if*}

{/if}
{/strip}
