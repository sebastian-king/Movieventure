-- Subset of the Movieventure schema relevant to the publicly-visible code.
-- Auth, media catalogue and playback-state tables. Drop into a fresh
-- database; the app handles per-row upserts from there.

CREATE TABLE IF NOT EXISTS users (
	id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
	username     VARCHAR(64)  NOT NULL UNIQUE,
	email        VARCHAR(255) NOT NULL UNIQUE,
	password     VARCHAR(255) NOT NULL,
	created      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_sessions (
	id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
	session_id    VARCHAR(64)  NOT NULL,
	session_name  VARCHAR(64)  NOT NULL,
	fingerprint   VARCHAR(64)  NOT NULL,
	uid           INT UNSIGNED NOT NULL,
	expires       INT UNSIGNED NOT NULL,
	session       TINYINT(1)   NOT NULL DEFAULT 0,
	PRIMARY KEY (id),
	KEY (session_id),
	KEY (session_name),
	KEY (uid),
	FOREIGN KEY (uid) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invitation_tokens (
	id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
	token     VARCHAR(64)  NOT NULL UNIQUE,
	email     VARCHAR(255) NOT NULL,
	expires   DATETIME     NOT NULL,
	created   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
	used      TINYINT(1)   NOT NULL DEFAULT 0,
	PRIMARY KEY (id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media (
	id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
	hash       CHAR(40)     NOT NULL UNIQUE,
	title      VARCHAR(255) NOT NULL,
	year       SMALLINT UNSIGNED NULL,
	imdb_id    VARCHAR(16)  NULL,
	added      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
	encoded_at DATETIME     NULL,
	PRIMARY KEY (id),
	KEY (imdb_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- The big one: per-(user, media) playback state. Written through by the
-- WebSocket bus at most once every five seconds during playback, plus a
-- final flush on disconnect. The composite primary key makes the upsert
-- in the bus a clean ON DUPLICATE KEY UPDATE.
CREATE TABLE IF NOT EXISTS playback_state (
	user_id        INT UNSIGNED NOT NULL,
	media_id       INT UNSIGNED NOT NULL,
	playhead       DOUBLE       NOT NULL DEFAULT 0,
	audio_track    INT          NULL,
	sub_track      INT          NULL,
	quality_level  INT          NULL,
	buffer_health  DOUBLE       NULL,
	viewing_stats  JSON         NULL,
	updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (user_id, media_id),
	KEY (updated_at),
	FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
	FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
