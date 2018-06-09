{strip}
{* Google analytics setup *}
{if $gBitSystem->isLive() && !$gBitUser->hasPermission( 'p_users_admin' )}
	{* **** GOOGLE ANALYTICS **** *}
	{if $gBitSystem->getConfig('google_analytics_ua')}
{literal}
<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

  ga('create', '{/literal}{$gBitSystem->getConfig('google_analytics_ua')}{literal}', 'auto');
{/literal}{if $gBitUser->isRegistered()}{literal}
  ga('set', '&uid', '{/literal}{$gBitUser->mUserId}{literal}'); // Set the user ID using signed-in user_id.
{/literal}{/if}{literal}
  ga('require', 'displayfeatures');
  ga('require', 'linkid', 'linkid.js');
  ga('send', 'pageview');
</script>
{/literal}

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
{/if}
{/strip}
