-- Stats 1.0.2: first-touch landing URL storage.
-- Idempotent. Same objects as admin/upgrades/1.0.2.php.

BEGIN;

CREATE SEQUENCE IF NOT EXISTS stats_landing_url_id_seq;

CREATE TABLE IF NOT EXISTS stats_landing_urls (
	landing_url_id integer PRIMARY KEY,
	landing_url varchar(4096) NOT NULL,
	landing_query varchar(4096)
);

ALTER TABLE stats_referer_users_map
	ADD COLUMN IF NOT EXISTS landing_url_id integer;

CREATE INDEX IF NOT EXISTS stats_referer_map_landing_idx
	ON stats_referer_users_map (landing_url_id);

INSERT INTO kernel_config (config_name, config_value, package)
SELECT 'package_stats_version', '1.0.2', 'stats'
WHERE NOT EXISTS (
	SELECT 1 FROM kernel_config WHERE config_name = 'package_stats_version'
);

UPDATE kernel_config
SET config_value = '1.0.2', package = 'stats'
WHERE config_name = 'package_stats_version';

COMMIT;

SELECT to_regclass('stats_landing_urls') AS landing_urls,
       to_regclass('stats_landing_url_id_seq') AS landing_seq,
       (SELECT config_value FROM kernel_config WHERE config_name = 'package_stats_version') AS package_stats_version;
