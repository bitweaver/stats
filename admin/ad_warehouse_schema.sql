-- Ad network warehouse (db2 / exampletest only until db1 sign-off).
-- Natural keys only: dump/upsert to db1 by (network, account, entity id).
-- Do not copy stats_landing_url_id sequences. User ids and order ids match clone and live.
-- Google/Bing/Meta/TikTok share these tables. Network-specific fields go in extra JSONB.
-- Our ROAS uses com_orders.order_total, never network_value / metrics.conversions_value.

CREATE TABLE IF NOT EXISTS ad_network (
	network_code text PRIMARY KEY,
	display_name text NOT NULL,
	timezone text NOT NULL DEFAULT 'America/Los_Angeles',
	currency char(3) NOT NULL DEFAULT 'USD',
	click_window_days integer,
	view_window_days integer,
	window_source text,
	extra jsonb NOT NULL DEFAULT '{}'::jsonb
);

INSERT INTO ad_network (network_code, display_name) VALUES
	('google', 'Google Ads'),
	('microsoft', 'Microsoft Advertising'),
	('meta', 'Meta Ads'),
	('tiktok', 'TikTok Ads')
ON CONFLICT (network_code) DO NOTHING;

ALTER TABLE ad_network ADD COLUMN IF NOT EXISTS click_window_days integer;
ALTER TABLE ad_network ADD COLUMN IF NOT EXISTS view_window_days integer;
ALTER TABLE ad_network ADD COLUMN IF NOT EXISTS window_source text;

-- Stated install setting: Google click-through conversion window is 30 days (max).
UPDATE ad_network
   SET click_window_days = 30, window_source = 'user'
 WHERE network_code = 'google'
   AND click_window_days IS NULL;

CREATE TABLE IF NOT EXISTS ad_account (
	network_code text NOT NULL REFERENCES ad_network (network_code),
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

CREATE TABLE IF NOT EXISTS ad_campaign (
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
	FOREIGN KEY (network_code, account_id) REFERENCES ad_account (network_code, account_id)
);

CREATE INDEX IF NOT EXISTS ad_campaign_name_idx
	ON ad_campaign (network_code, campaign_name);

-- Search ad groups and PMax asset groups. adgroup_kind distinguishes them.
CREATE TABLE IF NOT EXISTS ad_adgroup (
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
		REFERENCES ad_campaign (network_code, account_id, campaign_id)
);

CREATE TABLE IF NOT EXISTS ad_ad (
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
		REFERENCES ad_campaign (network_code, account_id, campaign_id)
);

CREATE TABLE IF NOT EXISTS ad_keyword (
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
		REFERENCES ad_adgroup (network_code, account_id, campaign_id, adgroup_id)
);

-- One row per date x grain. Unused dimensions are empty string (not NULL) so PK is simple.
-- grain=campaign is the ROAS spend grain. Finer grains are Search diagnostics.
CREATE TABLE IF NOT EXISTS ad_metrics_daily (
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
	CONSTRAINT ad_metrics_daily_grain_chk
		CHECK (grain IN ('campaign', 'adgroup', 'ad', 'keyword')),
	CONSTRAINT ad_metrics_daily_dims_chk CHECK (
		(grain = 'campaign' AND adgroup_id = '' AND ad_id = '' AND keyword_id = '')
		OR (grain = 'adgroup' AND adgroup_id <> '' AND ad_id = '' AND keyword_id = '')
		OR (grain = 'ad' AND ad_id <> '')
		OR (grain = 'keyword' AND keyword_id <> '')
	)
);

CREATE INDEX IF NOT EXISTS ad_metrics_daily_campaign_idx
	ON ad_metrics_daily (network_code, campaign_id, metric_date)
	WHERE grain = 'campaign';

-- First-touch from our landing (not Ads last-click). campaign_id is the network id when known.
-- Do not store stats landing_url_id; that sequence will not match db1.
CREATE TABLE IF NOT EXISTS ad_user_attribution (
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

CREATE INDEX IF NOT EXISTS ad_user_attribution_campaign_idx
	ON ad_user_attribution (network_code, campaign_id);

CREATE INDEX IF NOT EXISTS ad_user_attribution_name_idx
	ON ad_user_attribution (network_code, campaign_name);

-- Copy of first-touch at purchase (stable if the user later returns). Join order_total from com_orders.
CREATE TABLE IF NOT EXISTS ad_order_attribution (
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

CREATE INDEX IF NOT EXISTS ad_order_attribution_campaign_idx
	ON ad_order_attribution (network_code, campaign_id);

CREATE INDEX IF NOT EXISTS ad_order_attribution_user_idx
	ON ad_order_attribution (user_id);

CREATE INDEX IF NOT EXISTS ad_order_attribution_name_idx
	ON ad_order_attribution (network_code, campaign_name);

-- API credentials (JSON keys, refresh tokens) do not fit kernel_config C(250).
CREATE TABLE IF NOT EXISTS ad_api_secret (
	secret_name text PRIMARY KEY,
	secret_value text NOT NULL,
	updated_at timestamptz NOT NULL DEFAULT now()
);
