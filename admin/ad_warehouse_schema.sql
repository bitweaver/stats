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

UPDATE stats_ad_network
   SET click_window_days = 90, window_source = 'user'
 WHERE network_code = 'google'
   AND (click_window_days IS NULL OR click_window_days < 90);

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
