{* **** GOOGLE TAG MANAGER **** *}
{if $gBitSystem->getConfig('google_tagmanager_id')}
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$gBitSystem->getConfig('google_tagmanager_id')}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
{/if}

{* **** MICROSOFT ANALYTICS **** *}
{if $gBitSystem->getConfig('microsoft_analytics_ti')}
<noscript><img src="//bat.bing.com/action/0?ti={$gBitSystem->getConfig('microsoft_analytics_ti')}&Ver=2" height="0" width="0" style="display:none; visibility: hidden;" alt="."></noscript>
{/if}

