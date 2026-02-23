CREATE DATABASE IF NOT EXISTS gra_browserowa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gra_browserowa;

CREATE TABLE IF NOT EXISTS players (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nick VARCHAR(24) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) DEFAULT NULL,
    level INT UNSIGNED NOT NULL DEFAULT 1,
    xp BIGINT UNSIGNED NOT NULL DEFAULT 0,
    money BIGINT UNSIGNED NOT NULL DEFAULT 0,
    fatigue TINYINT UNSIGNED NOT NULL DEFAULT 0,
    fatigue_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    rank_tier ENUM('silver','gold','kalach','supreme','global') NOT NULL DEFAULT 'silver',
    aim INT UNSIGNED NOT NULL DEFAULT 10,
    refleks INT UNSIGNED NOT NULL DEFAULT 10,
    mobilnosc INT UNSIGNED NOT NULL DEFAULT 10,
    kontrola_odrzutu INT UNSIGNED NOT NULL DEFAULT 10,
    granaty INT UNSIGNED NOT NULL DEFAULT 10,
    szczescie INT UNSIGNED NOT NULL DEFAULT 10,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS player_map_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    rank_tier ENUM('silver','gold','kalach','supreme','global') NOT NULL,
    map_name VARCHAR(64) NOT NULL,
    won_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_player_rank_map (player_id, rank_tier, map_name),
    INDEX idx_player_rank (player_id, rank_tier),
    CONSTRAINT fk_progress_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
);
