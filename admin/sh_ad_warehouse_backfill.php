<?php
/**
 * Backfill stats_ad_user_attribution / stats_ad_order_attribution from first-touch landings.
 * Deployed with stats. Secrets from Ad API setup. No conversion upload.
 *
 *   php stats/admin/sh_ad_warehouse_backfill.php --site_name=example
 */
chdir( dirname( __FILE__ ) );
$gShellScript = true;
$gLightweightScan = true;
foreach( array( 'IS_DEV', 'IS_LIVE', 'IS_SANDBOX', 'SITE_NAME' ) as $k ) {
	$v = getenv( $k );
	if( $v !== false && $v !== '' && empty( $_SERVER[$k] ) ) {
		$_SERVER[$k] = $v;
	}
}
require_once dirname( __FILE__ ).'/../../config/kernel/cron_setup_inc.php';
require_once STATS_PKG_INCLUDE_PATH.'ads_api_lib.php';
require_once STATS_PKG_INCLUDE_PATH.'ads_warehouse_lib.php';
ads_refuse_upload( $argv );
$row = ads_require_warehouse_db( $_SERVER );
fwrite( STDERR, "backfill attribution on {$row['addr']} {$row['db']}\n" );
ads_apply_warehouse_schema();

$db = $gBitSystem->mDb;
$googleAccount = ads_customer_id();

$sql = "SELECT m.user_id, slu.landing_url, slu.landing_query
	FROM stats_referer_users_map m
	JOIN stats_landing_urls slu ON slu.landing_url_id = m.landing_url_id
	WHERE m.landing_url_id IS NOT NULL";
$users = $db->getAll( $sql );
fwrite( STDERR, "mapped users with landing: ".count( $users )."\n" );

$nUser = 0;
$nPaidUntracked = 0;
$db->StartTrans();
foreach( $users as $u ) {
	$keys = ads_parse_landing_keys( $u['landing_query'], $u['landing_url'] );
	$account = null;
	if( $keys['network_code'] === 'google' && $googleAccount ) {
		$account = $googleAccount;
	}
	$extra = array();
	if( !empty( $keys['untracked_paid'] ) ) {
		$extra['untracked_paid'] = true;
		$nPaidUntracked++;
	}
	ads_upsert_touch(
		'stats_ad_user_attribution',
		array( 'user_id' ),
		array(
			'user_id'       => (int)$u['user_id'],
			'network_code'  => $keys['network_code'],
			'account_id'    => $account,
			'campaign_id'   => $keys['campaign_id'],
			'campaign_name' => $keys['campaign_name'] !== '' ? $keys['campaign_name'] : null,
			'adgroup_id'    => $keys['adgroup_id'],
			'adgroup_name'  => $keys['adgroup_name'] !== '' ? $keys['adgroup_name'] : null,
			'keyword_id'    => $keys['keyword_id'],
			'keyword_text'  => $keys['keyword_text'] !== '' ? $keys['keyword_text'] : null,
			'click_id'      => $keys['click_id'],
			'landing_url'   => $u['landing_url'],
			'landing_query' => $u['landing_query'],
			'source'        => 'landing_first_touch',
			'attributed_at' => date( 'c' ),
			'extra'         => json_encode( $extra ),
		),
		array(
			'network_code', 'account_id', 'campaign_id', 'campaign_name',
			'adgroup_id', 'adgroup_name', 'keyword_id', 'keyword_text',
			'click_id', 'landing_url', 'landing_query', 'source', 'attributed_at', 'extra',
		)
	);
	$nUser++;
	if( $nUser % 5000 === 0 ) {
		fwrite( STDERR, "  users $nUser\n" );
	}
}
$db->CompleteTrans();
fwrite( STDERR, "upserted $nUser user attributions (untracked paid $nPaidUntracked)\n" );

// Click ids the network could resolve (stats_ad_click) name the campaign
// for landings without tracking keys and give every matched user a real
// click date. Landing-resolved users keep their source; only the date is added.
if( ads_warehouse_table_exists( 'stats_ad_click' ) ) {
	$nClick = $db->getOne(
		"SELECT COUNT(*) FROM stats_ad_user_attribution a
		 JOIN stats_ad_click k ON k.network_code = a.network_code AND k.click_id = a.click_id
		 WHERE a.click_id IS NOT NULL
		   AND (a.campaign_id IS NULL OR a.campaign_id <> k.campaign_id OR a.click_date IS NULL)"
	);
	$db->query(
		"UPDATE stats_ad_user_attribution a
		    SET campaign_id = k.campaign_id,
		        campaign_name = COALESCE(c.campaign_name, a.campaign_name),
		        account_id = COALESCE(a.account_id, k.account_id),
		        adgroup_id = COALESCE(a.adgroup_id, k.adgroup_id),
		        keyword_id = COALESCE(a.keyword_id, k.keyword_id),
		        keyword_text = COALESCE(a.keyword_text, k.keyword_text),
		        click_date = k.click_date,
		        source = CASE WHEN a.campaign_id IS NULL OR a.campaign_id <> k.campaign_id THEN 'click_view' ELSE a.source END,
		        extra = CASE WHEN a.campaign_id IS NULL OR a.campaign_id <> k.campaign_id
		                     THEN (a.extra - 'untracked_paid') || jsonb_build_object('resolved_by', 'click_view')
		                     ELSE a.extra || jsonb_build_object('click_matched', true) END
		   FROM stats_ad_click k
		   LEFT JOIN stats_ad_campaign c
		     ON c.network_code = k.network_code AND c.account_id = k.account_id AND c.campaign_id = k.campaign_id
		  WHERE a.network_code = k.network_code AND a.click_id = k.click_id
		    AND (a.campaign_id IS NULL OR a.campaign_id <> k.campaign_id OR a.click_date IS NULL)"
	);
	fwrite( STDERR, "resolved $nClick user attributions from stored clicks\n" );
}

fwrite( STDERR, "copy first-touch onto paid orders\n" );
$db->query(
	"INSERT INTO stats_ad_order_attribution (
		orders_id, user_id, network_code, account_id, campaign_id, campaign_name,
		adgroup_id, adgroup_name, keyword_id, keyword_text, click_id, click_date, source, attributed_at, extra
	)
	SELECT o.orders_id, a.user_id, a.network_code, a.account_id, a.campaign_id, a.campaign_name,
		a.adgroup_id, a.adgroup_name, a.keyword_id, a.keyword_text, a.click_id, a.click_date,
		a.source, now(), a.extra || jsonb_build_object('date_purchased', o.date_purchased)
	FROM ".BIT_DB_PREFIX."com_orders o
	JOIN stats_ad_user_attribution a ON a.user_id = o.customers_id
	WHERE o.orders_status_id > 0
	ON CONFLICT (orders_id) DO UPDATE SET
		user_id = EXCLUDED.user_id,
		network_code = EXCLUDED.network_code,
		account_id = EXCLUDED.account_id,
		campaign_id = EXCLUDED.campaign_id,
		campaign_name = EXCLUDED.campaign_name,
		adgroup_id = EXCLUDED.adgroup_id,
		adgroup_name = EXCLUDED.adgroup_name,
		keyword_id = EXCLUDED.keyword_id,
		keyword_text = EXCLUDED.keyword_text,
		click_id = EXCLUDED.click_id,
		click_date = EXCLUDED.click_date,
		source = EXCLUDED.source,
		attributed_at = EXCLUDED.attributed_at,
		extra = EXCLUDED.extra"
);
$nOrd = $db->getOne( 'SELECT COUNT(*) FROM stats_ad_order_attribution' );
fwrite( STDERR, "order attributions: $nOrd\n" );
