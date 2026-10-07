-- Stats ad warehouse. Tables are stats_* (package prefix).
-- Natural keys only. Do not copy stats_landing_urls sequences.
-- ROAS uses com_orders.order_total, never network_value.

CREATE TABLE IF NOT EXISTS stats_prefs (
	pref_name text PRIMARY KEY,
	pref_value text NOT NULL,
	updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS stats_ad_network (
	network_code text PRIMARY KEY,
	display_name text NOT NULL,
	timezone text NOT NULL DEFAULT 'America/Los_Angeles',
	currency char(3) NOT NULL DEFAULT 'USD',
	click_window_days integer,
	view_window_days integer,
	window_source text,
	extra jsonb NOT NULL DEFAULT '{}'::jsonb
);

INSERT INTO stats_ad_network (network_code, display_name) VALUES
	('google', 'Google Ads'),
	('microsoft', 'Microsoft Advertising'),
	('meta', 'Meta Ads'),
	('tiktok', 'TikTok Ads')
ON CONFLICT (network_code) DO NOTHING;

ALTER TABLE stats_ad_network ADD COLUMN IF NOT EXISTS click_window_days integer;
ALTER TABLE stats_ad_network ADD COLUMN IF NOT EXISTS view_window_days integer;
ALTER TABLE stats_ad_network ADD COLUMN IF NOT EXISTS window_source text;

-- Seed a window only when none is stored. 'default' is replaced by the
-- conversion-action catalog ('network') or by the ROAS page ('user').
UPDATE stats_ad_network
   SET click_window_days = 90, window_source = 'default'
 WHERE network_code = 'google'
   AND click_window_days IS NULL;

-- Earlier schema versions wrote 90/'user' unconditionally; reclassify that
-- seed so the catalog-derived window can take over.
UPDATE stats_ad_network
   SET window_source = 'default'
 WHERE network_code = 'google'
   AND window_source = 'user'
   AND click_window_days = 90;

CREATE TABLE IF NOT EXISTS stats_ad_account (
	network_code text NOT NULL REFERENCES stats_ad_network (network_code),
	account_id text NOT NULL,
	account_name text,
	is_manager boolean NOT NULL DEFAULT false,
	status text,
	currency char(3),
	timezone text,
	extra jsonb NOT NULL DEFAULT '{}'::jsonb,
	first_seen_at timestamptz NOT NULL DEFAULT now(),
	last_seen_at timestamptz NOT NULL DEFAULT now(),
	PRIMARY KEY (network_code, account_id)
);

CREATE TABLE IF NOT EXISTS stats_ad_campaign (
	network_code text NOT NULL,
	account_id text NOT NULL,
	campaign_id text NOT NULL,
	campaign_name text,
	channel text,
	status text,
	bidding_strategy text,
	target_roas numeric(10, 4),
	extra jsonb NOT NULL DEFAULT '{}'::jsonb,
	first_seen_at timestamptz NOT NULL DEFAULT now(),
	last_seen_at timestamptz NOT NULL DEFAULT now(),
	PRIMARY KEY (network_code, account_id, campaign_id),
	FOREIGN KEY (network_code, account_id) REFERENCES stats_ad_account (network_code, account_id)
);

CREATE INDEX IF NOT EXISTS stats_ad_campaign_name_idx
	ON stats_ad_campaign (network_code, campaign_name);

CREATE TABLE IF NOT EXISTS stats_ad_adgroup (
	network_code text NOT NULL,
	account_id text NOT NULL,
	campaign_id text NOT NULL,
	adgroup_id text NOT NULL,
	adgroup_name text,
	adgroup_kind text NOT NULL DEFAULT 'adgroup',
	status text,
	extra jsonb NOT NULL DEFAULT '{}'::jsonb,
	first_seen_at timestamptz NOT NULL DEFAULT now(),
	last_seen_at timestamptz NOT NULL DEFAULT now(),
	PRIMARY KEY (network_code, account_id, campaign_id, adgroup_id),
	FOREIGN KEY (network_code, account_id, campaign_id)
		REFERENCES stats_ad_campaign (network_code, account_id, campaign_id)
);

CREATE TABLE IF NOT EXISTS stats_ad_ad (
	network_code text NOT NULL,
	account_id text NOT NULL,
	campaign_id text NOT NULL,
	adgroup_id text NOT NULL DEFAULT '',
	ad_id text NOT NULL,
	ad_name text,
	ad_type text,
	status text,
	extra jsonb NOT NULL DEFAULT '{}'::jsonb,
	first_seen_at timestamptz NOT NULL DEFAULT now(),
	last_seen_at timestamptz NOT NULL DEFAULT now(),
	PRIMARY KEY (network_code, account_id, campaign_id, adgroup_id, ad_id),
	FOREIGN KEY (network_code, account_id, campaign_id)
		REFERENCES stats_ad_campaign (network_code, account_id, campaign_id)
);

CREATE TABLE IF NOT EXISTS stats_ad_keyword (
	network_code text NOT NULL,
	account_id text NOT NULL,
	campaign_id text NOT NULL,
	adgroup_id text NOT NULL,
	keyword_id text NOT NULL,
	keyword_text text,
	match_type text,
	status text,
	extra jsonb NOT NULL DEFAULT '{}'::jsonb,
	first_seen_at timestamptz NOT NULL DEFAULT now(),
	last_seen_at timestamptz NOT NULL DEFAULT now(),
	PRIMARY KEY (network_code, account_id, campaign_id, adgroup_id, keyword_id),
	FOREIGN KEY (network_code, account_id, campaign_id, adgroup_id)
		REFERENCES stats_ad_adgroup (network_code, account_id, campaign_id, adgroup_id)
);

CREATE TABLE IF NOT EXISTS stats_ad_metrics_daily (
	metric_date date NOT NULL,
	network_code text NOT NULL,
	account_id text NOT NULL,
	grain text NOT NULL,
	campaign_id text NOT NULL,
	adgroup_id text NOT NULL DEFAULT '',
	ad_id text NOT NULL DEFAULT '',
	keyword_id text NOT NULL DEFAULT '',
	spend numeric(14, 4) NOT NULL DEFAULT 0,
	clicks bigint NOT NULL DEFAULT 0,
	impressions bigint NOT NULL DEFAULT 0,
	network_conversions double precision,
	network_value numeric(14, 4),
	currency char(3) NOT NULL DEFAULT 'USD',
	extra jsonb NOT NULL DEFAULT '{}'::jsonb,
	pulled_at timestamptz NOT NULL DEFAULT now(),
	PRIMARY KEY (metric_date, network_code, account_id, grain, campaign_id, adgroup_id, ad_id, keyword_id),
	CONSTRAINT stats_ad_metrics_daily_grain_chk
		CHECK (grain IN ('campaign', 'adgroup', 'ad', 'keyword')),
	CONSTRAINT stats_ad_metrics_daily_dims_chk CHECK (
		(grain = 'campaign' AND adgroup_id = '' AND ad_id = '' AND keyword_id = '')
		OR (grain = 'adgroup' AND adgroup_id <> '' AND ad_id = '' AND keyword_id = '')
		OR (grain = 'ad' AND ad_id <> '')
		OR (grain = 'keyword' AND keyword_id <> '')
	)
);

CREATE INDEX IF NOT EXISTS stats_ad_metrics_daily_campaign_idx
	ON stats_ad_metrics_daily (network_code, campaign_id, metric_date)
	WHERE grain = 'campaign';

CREATE TABLE IF NOT EXISTS stats_ad_user_attribution (
	user_id bigint PRIMARY KEY,
	network_code text,
	account_id text,
	campaign_id text,
	campaign_name text,
	adgroup_id text,
	adgroup_name text,
	keyword_id text,
	keyword_text text,
	click_id text,
	landing_url text,
	landing_query text,
	source text NOT NULL,
	attributed_at timestamptz NOT NULL DEFAULT now(),
	extra jsonb NOT NULL DEFAULT '{}'::jsonb
);

CREATE INDEX IF NOT EXISTS stats_ad_user_attribution_campaign_idx
	ON stats_ad_user_attribution (network_code, campaign_id);

CREATE INDEX IF NOT EXISTS stats_ad_user_attribution_name_idx
	ON stats_ad_user_attribution (network_code, campaign_name);

CREATE TABLE IF NOT EXISTS stats_ad_order_attribution (
	orders_id bigint PRIMARY KEY,
	user_id bigint NOT NULL,
	network_code text,
	account_id text,
	campaign_id text,
	campaign_name text,
	adgroup_id text,
	adgroup_name text,
	keyword_id text,
	keyword_text text,
	click_id text,
	source text NOT NULL,
	attributed_at timestamptz NOT NULL DEFAULT now(),
	extra jsonb NOT NULL DEFAULT '{}'::jsonb
);

CREATE INDEX IF NOT EXISTS stats_ad_order_attribution_campaign_idx
	ON stats_ad_order_attribution (network_code, campaign_id);

CREATE INDEX IF NOT EXISTS stats_ad_order_attribution_user_idx
	ON stats_ad_order_attribution (user_id);

CREATE INDEX IF NOT EXISTS stats_ad_order_attribution_name_idx
	ON stats_ad_order_attribution (network_code, campaign_name);

-- Campaign settings snapshot (mutable) + daily history.
ALTER TABLE stats_ad_campaign ADD COLUMN IF NOT EXISTS target_cpa numeric(14, 4);
ALTER TABLE stats_ad_campaign ADD COLUMN IF NOT EXISTS budget_amount numeric(14, 4);
ALTER TABLE stats_ad_campaign ADD COLUMN IF NOT EXISTS bidding_strategy_id text;
ALTER TABLE stats_ad_campaign ADD COLUMN IF NOT EXISTS primary_status text;

CREATE TABLE IF NOT EXISTS stats_ad_campaign_settings_daily (
	snapshot_date date NOT NULL,
	network_code text NOT NULL,
	account_id text NOT NULL,
	campaign_id text NOT NULL,
	campaign_name text,
	status text,
	serving_status text,
	primary_status text,
	primary_status_reasons text[],
	channel text,
	bidding_strategy_type text,
	bidding_scope text,
	bidding_strategy_id text,
	bidding_strategy_name text,
	target_roas numeric(10, 4),
	target_cpa numeric(14, 4),
	budget_amount numeric(14, 4),
	budget_delivery text,
	budget_period text,
	budget_shared boolean,
	extra jsonb NOT NULL DEFAULT '{}'::jsonb,
	pulled_at timestamptz NOT NULL DEFAULT now(),
	PRIMARY KEY (snapshot_date, network_code, account_id, campaign_id)
);

CREATE INDEX IF NOT EXISTS stats_ad_campaign_settings_daily_campaign_idx
	ON stats_ad_campaign_settings_daily (network_code, campaign_id, snapshot_date);

-- Conversion metrics the network reports two ways: credited to the click
-- date (network_conversions / network_value) and to the conversion date.
ALTER TABLE stats_ad_metrics_daily ADD COLUMN IF NOT EXISTS all_conversions double precision;
ALTER TABLE stats_ad_metrics_daily ADD COLUMN IF NOT EXISTS all_conversions_value numeric(14, 4);
ALTER TABLE stats_ad_metrics_daily ADD COLUMN IF NOT EXISTS conversions_by_conv_date double precision;
ALTER TABLE stats_ad_metrics_daily ADD COLUMN IF NOT EXISTS value_by_conv_date numeric(14, 4);
-- Search impression share ratios (campaign grain). Null for channels that
-- do not report them. The network clamps to 0.0999 (<10%) and 0.9001 (>90%).
ALTER TABLE stats_ad_metrics_daily ADD COLUMN IF NOT EXISTS search_is double precision;
ALTER TABLE stats_ad_metrics_daily ADD COLUMN IF NOT EXISTS search_budget_lost_is double precision;
ALTER TABLE stats_ad_metrics_daily ADD COLUMN IF NOT EXISTS search_rank_lost_is double precision;

-- Conversion action catalog. click_window_days here is the network's own
-- click-through lookback for that action.
CREATE TABLE IF NOT EXISTS stats_ad_conversion_action (
	network_code text NOT NULL,
	account_id text NOT NULL,
	conversion_action_id text NOT NULL,
	action_name text,
	category text,
	action_type text,
	origin text,
	status text,
	primary_for_goal boolean,
	include_in_conversions boolean,
	click_window_days integer,
	view_window_days integer,
	attribution_model text,
	default_value numeric(14, 4),
	counting_type text,
	extra jsonb NOT NULL DEFAULT '{}'::jsonb,
	first_seen_at timestamptz NOT NULL DEFAULT now(),
	last_seen_at timestamptz NOT NULL DEFAULT now(),
	PRIMARY KEY (network_code, account_id, conversion_action_id)
);

-- Per conversion action per campaign per day. Never holds cost, clicks or
-- impressions: the network does not allow those with conversion segments.
CREATE TABLE IF NOT EXISTS stats_ad_conversion_daily (
	metric_date date NOT NULL,
	network_code text NOT NULL,
	account_id text NOT NULL,
	campaign_id text NOT NULL,
	conversion_action_id text NOT NULL,
	conversion_action_name text,
	conversion_action_category text,
	conversions double precision,
	conversions_value numeric(14, 4),
	all_conversions double precision,
	all_conversions_value numeric(14, 4),
	conversions_by_conv_date double precision,
	value_by_conv_date numeric(14, 4),
	currency char(3) NOT NULL DEFAULT 'USD',
	extra jsonb NOT NULL DEFAULT '{}'::jsonb,
	pulled_at timestamptz NOT NULL DEFAULT now(),
	PRIMARY KEY (metric_date, network_code, account_id, campaign_id, conversion_action_id)
);

CREATE INDEX IF NOT EXISTS stats_ad_conversion_daily_campaign_idx
	ON stats_ad_conversion_daily (network_code, campaign_id, metric_date);

-- Click ids the network can still resolve (Google keeps 90 days). Never
-- purged: users register months after the click.
CREATE TABLE IF NOT EXISTS stats_ad_click (
	network_code text NOT NULL,
	account_id text NOT NULL,
	click_id text NOT NULL,
	click_date date NOT NULL,
	campaign_id text NOT NULL,
	adgroup_id text,
	ad_id text,
	keyword_id text,
	keyword_text text,
	match_type text,
	click_type text,
	network_type text,
	device text,
	extra jsonb NOT NULL DEFAULT '{}'::jsonb,
	pulled_at timestamptz NOT NULL DEFAULT now(),
	PRIMARY KEY (network_code, account_id, click_id)
);

CREATE INDEX IF NOT EXISTS stats_ad_click_date_idx
	ON stats_ad_click (network_code, click_date);

ALTER TABLE stats_ad_user_attribution ADD COLUMN IF NOT EXISTS click_date date;
ALTER TABLE stats_ad_order_attribution ADD COLUMN IF NOT EXISTS click_date date;

CREATE INDEX IF NOT EXISTS stats_ad_user_attribution_click_idx
	ON stats_ad_user_attribution (network_code, click_id)
	WHERE click_id IS NOT NULL;

-- Legacy tracking labels that are not current campaign names, mapped by
-- staff to a campaign id. Consulted after exact-name matching.
CREATE TABLE IF NOT EXISTS stats_ad_campaign_alias (
	network_code text NOT NULL,
	alias_name text NOT NULL,
	campaign_id text NOT NULL,
	updated_at timestamptz NOT NULL DEFAULT now(),
	PRIMARY KEY (network_code, alias_name)
);
