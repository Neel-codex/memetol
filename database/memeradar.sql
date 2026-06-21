-- =====================================================================
--  MemeRadar AI - MySQL Database Schema
--  Compatible with MySQL 5.7+ / MariaDB 10.3+ (shared cPanel hosting)
--  Charset: utf8mb4
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(50)  NOT NULL,
    `email`      VARCHAR(190) NOT NULL,
    `password`   VARCHAR(255) NOT NULL,
    `plan`       VARCHAR(30)  NOT NULL DEFAULT 'free',
    `status`     ENUM('active','pending','banned') NOT NULL DEFAULT 'active',
    `avatar`     VARCHAR(255) DEFAULT NULL,
    `is_verified` TINYINT(1)  NOT NULL DEFAULT 1,
    `plan_expires_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login` DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    UNIQUE KEY `uq_users_username` (`username`),
    KEY `idx_users_plan` (`plan`),
    KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Admins (separate login system)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(50)  NOT NULL,
    `email`      VARCHAR(190) DEFAULT NULL,
    `password`   VARCHAR(255) NOT NULL,
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login` DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Remember-me tokens
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `selector`   CHAR(16)     NOT NULL,
    `token`      CHAR(64)     NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_remember_selector` (`selector`),
    KEY `idx_remember_user` (`user_id`),
    CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Password reset tokens
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`      VARCHAR(190) NOT NULL,
    `token`      CHAR(64)     NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pwreset_email` (`email`),
    KEY `idx_pwreset_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Coins
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `coins` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `contract`         VARCHAR(120) NOT NULL,
    `name`             VARCHAR(120) NOT NULL,
    `symbol`           VARCHAR(32)  NOT NULL,
    `chain`            VARCHAR(30)  NOT NULL,
    `price`            DECIMAL(30,12) NOT NULL DEFAULT 0,
    `price_change_24h` DECIMAL(12,2)  NOT NULL DEFAULT 0,
    `market_cap`       DECIMAL(30,2)  NOT NULL DEFAULT 0,
    `liquidity`        DECIMAL(30,2)  NOT NULL DEFAULT 0,
    `volume`           DECIMAL(30,2)  NOT NULL DEFAULT 0,
    `pair_age_hours`   DECIMAL(12,1)  NOT NULL DEFAULT 0,
    `buys_24h`         INT NOT NULL DEFAULT 0,
    `sells_24h`        INT NOT NULL DEFAULT 0,
    `buy_sell_ratio`   DECIMAL(12,2)  NOT NULL DEFAULT 0,
    `holders`          INT NOT NULL DEFAULT 0,
    `whale_percent`    DECIMAL(6,2)   NOT NULL DEFAULT 0,
    `social_score`     INT NOT NULL DEFAULT 0,
    `liquidity_locked` TINYINT(1) NOT NULL DEFAULT 0,
    `smart_money_buys` INT NOT NULL DEFAULT 0,
    `mint_enabled`     TINYINT(1) NOT NULL DEFAULT 0,
    `is_honeypot`      TINYINT(1) NOT NULL DEFAULT 0,
    `owner_privileges` TINYINT(1) NOT NULL DEFAULT 0,
    `has_blacklist`    TINYINT(1) NOT NULL DEFAULT 0,
    `buy_tax`          DECIMAL(6,2) NOT NULL DEFAULT 0,
    `sell_tax`         DECIMAL(6,2) NOT NULL DEFAULT 0,
    `ai_score`         INT NOT NULL DEFAULT 0,
    `risk_level`       ENUM('LOW','MEDIUM','HIGH') NOT NULL DEFAULT 'MEDIUM',
    `warnings`         TEXT,
    `logo`             VARCHAR(255) DEFAULT NULL,
    `pair_url`         VARCHAR(255) DEFAULT NULL,
    `is_featured`      TINYINT(1) NOT NULL DEFAULT 0,
    `is_hidden`        TINYINT(1) NOT NULL DEFAULT 0,
    `views`            INT NOT NULL DEFAULT 0,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_coin_chain_contract` (`chain`,`contract`),
    KEY `idx_coins_symbol` (`symbol`),
    KEY `idx_coins_chain` (`chain`),
    KEY `idx_coins_ai` (`ai_score`),
    KEY `idx_coins_volume` (`volume`),
    KEY `idx_coins_created` (`created_at`),
    KEY `idx_coins_featured` (`is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Watchlist
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `watchlist` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `coin_id`    INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_watch_user_coin` (`user_id`,`coin_id`),
    KEY `idx_watch_coin` (`coin_id`),
    CONSTRAINT `fk_watch_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_watch_coin` FOREIGN KEY (`coin_id`) REFERENCES `coins`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Alerts
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `alerts` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `coin_id`    INT UNSIGNED DEFAULT NULL,
    `type`       ENUM('price','volume','new_listing','ai_buy') NOT NULL DEFAULT 'price',
    `condition_type` ENUM('above','below') NOT NULL DEFAULT 'above',
    `threshold`  DECIMAL(30,12) NOT NULL DEFAULT 0,
    `channel`    SET('browser','telegram','email') NOT NULL DEFAULT 'browser',
    `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
    `last_triggered_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_alerts_user` (`user_id`),
    KEY `idx_alerts_coin` (`coin_id`),
    CONSTRAINT `fk_alerts_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_alerts_coin` FOREIGN KEY (`coin_id`) REFERENCES `coins`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Smart money tracker
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `smart_money` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `wallet`        VARCHAR(120) NOT NULL,
    `label`         VARCHAR(80) DEFAULT NULL,
    `token_symbol`  VARCHAR(32) NOT NULL,
    `token_contract` VARCHAR(120) DEFAULT NULL,
    `chain`         VARCHAR(30) NOT NULL,
    `amount_usd`    DECIMAL(30,2) NOT NULL DEFAULT 0,
    `est_profit`    DECIMAL(30,2) NOT NULL DEFAULT 0,
    `action`        ENUM('buy','sell') NOT NULL DEFAULT 'buy',
    `tx_date`       DATETIME NOT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sm_wallet` (`wallet`),
    KEY `idx_sm_chain` (`chain`),
    KEY `idx_sm_date` (`tx_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Wallet follows
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wallet_follows` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`   INT UNSIGNED NOT NULL,
    `wallet`    VARCHAR(120) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_follow_user_wallet` (`user_id`,`wallet`),
    CONSTRAINT `fk_follow_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Plans
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `plans` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`       VARCHAR(30) NOT NULL,
    `name`       VARCHAR(50) NOT NULL,
    `price`      DECIMAL(10,2) NOT NULL DEFAULT 0,
    `duration_days` INT NOT NULL DEFAULT 30,
    `features`   TEXT,
    `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_plans_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Ads
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ads` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(120) NOT NULL,
    `placement`  ENUM('banner','sidebar','popup','adsense','html') NOT NULL DEFAULT 'banner',
    `code`       TEXT,
    `image`      VARCHAR(255) DEFAULT NULL,
    `link`       VARCHAR(255) DEFAULT NULL,
    `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ads_placement` (`placement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Banners
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `banners` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(120) NOT NULL,
    `position`   ENUM('hero','trending','mobile') NOT NULL DEFAULT 'hero',
    `image`      VARCHAR(255) DEFAULT NULL,
    `link`       VARCHAR(255) DEFAULT NULL,
    `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
    `starts_at`  DATETIME DEFAULT NULL,
    `ends_at`    DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_banners_position` (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- API settings
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_settings` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`      VARCHAR(50) NOT NULL,
    `base_url`  VARCHAR(255) DEFAULT NULL,
    `api_key`   VARCHAR(255) DEFAULT NULL,
    `enabled`   TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_api_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Settings (key/value)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `key`   VARCHAR(80) NOT NULL,
    `value` TEXT,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Logs
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logs` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`      VARCHAR(40) NOT NULL DEFAULT 'info',
    `message`   TEXT,
    `user_id`   INT UNSIGNED DEFAULT NULL,
    `ip`        VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_logs_type` (`type`),
    KEY `idx_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  SEED DATA
-- =====================================================================

-- Admin user (username: admin / password: admin123) - forced to change on first login.
-- Hash below is bcrypt of "admin123".
INSERT INTO `admins` (`username`,`email`,`password`,`must_change_password`)
VALUES ('admin','admin@memeradar.ai','$2y$12$oAHZU5mMAXOZ9jtweFLh5eRu5xvBHCuL2DhIOhJoLihHQ32GSLhdO', 1)
ON DUPLICATE KEY UPDATE `username` = `username`;

-- Demo user (username: demo / password: demo123)
INSERT INTO `users` (`username`,`email`,`password`,`plan`,`status`)
VALUES ('demo','demo@memeradar.ai','$2y$12$3KsLx65kRGslibjJpZydH.tHbroI0.UASqyDDI6cwRVoSpPQTNpki','free','active')
ON DUPLICATE KEY UPDATE `username` = `username`;

-- Plans
INSERT INTO `plans` (`slug`,`name`,`price`,`duration_days`,`features`,`is_active`,`sort_order`) VALUES
('free','Free',0.00,3650,'Basic scanner|10 watchlist coins|Delayed data|Community alerts',1,1),
('pro','Pro',19.00,30,'Real-time scanner|Unlimited watchlist|AI risk scores|Telegram alerts|Smart money tracker',1,2),
('premium','Premium',49.00,30,'Everything in Pro|Priority scanning|Rug-pull detector|API access|Whale alerts|Early signals',1,3)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- API settings
INSERT INTO `api_settings` (`name`,`base_url`,`enabled`) VALUES
('dexscreener','https://api.dexscreener.com/latest/dex',1),
('geckoterminal','https://api.geckoterminal.com/api/v2',1),
('coingecko','https://api.coingecko.com/api/v3',1)
ON DUPLICATE KEY UPDATE `base_url` = VALUES(`base_url`);

-- Settings
INSERT INTO `settings` (`key`,`value`) VALUES
('site_name','MemeRadar AI'),
('seo_title','MemeRadar AI - Real-Time AI Meme Coin Scanner'),
('seo_description','Detect new meme coins, analyze rug-pull risk, track smart money wallets and get AI-powered trading signals across Ethereum, BNB, Solana, Base and Polygon.'),
('seo_keywords','meme coin scanner, ai crypto, rug pull detector, smart money tracker, new token alerts, dexscreener'),
('logo',''),
('favicon',''),
('maintenance_mode','0'),
('theme_primary','#00ff99'),
('theme_secondary','#00c3ff'),
('theme_accent','#ffcc00'),
('telegram_enabled','0'),
('telegram_bot_token',''),
('telegram_chat_id',''),
('telegram_webhook',''),
('smtp_host',''),
('smtp_port','587'),
('smtp_user',''),
('smtp_pass',''),
('smtp_from','no-reply@memeradar.ai'),
('last_scan_at','')
ON DUPLICATE KEY UPDATE `value` = `settings`.`value`;

-- Demo coins
INSERT INTO `coins`
(`contract`,`name`,`symbol`,`chain`,`price`,`price_change_24h`,`market_cap`,`liquidity`,`volume`,`pair_age_hours`,`buys_24h`,`sells_24h`,`buy_sell_ratio`,`holders`,`whale_percent`,`social_score`,`liquidity_locked`,`smart_money_buys`,`ai_score`,`risk_level`,`warnings`,`is_featured`)
VALUES
('0x6982508145454ce325ddbe47a25d4ec3d2311933','Pepe','PEPE','ethereum',0.00000112,4.20,470000000,8200000,120000000,8760,42000,38000,1.10,210000,7.50,92,1,5,86,'LOW','[]',1),
('0x95ad61b0a150d79219dcf64e1e6cc01f0b64c4ce','Shiba Inu','SHIB','ethereum',0.00000812,-1.30,4800000000,15000000,210000000,26280,55000,57000,0.96,1300000,5.20,90,1,4,84,'LOW','[]',1),
('DezXAZ8z7PnrnRJjz3wXBoRgixCa6xjnB7YaB1pPB263','Bonk','BONK','solana',0.00001621,8.90,1100000000,4200000,89000000,13140,33000,29000,1.14,650000,9.10,80,1,3,78,'LOW','[]',1),
('EKpQGSJtjMFqKZ9KQanSqYXRcF8fBopzLHYxdM65zcjm','dogwifhat','WIF','solana',1.82,-3.40,1800000000,9800000,140000000,8760,28000,30000,0.93,180000,12.00,76,1,2,71,'LOW','[]',0),
('0x4d224452801ACEd8B2F0aebE155379bb5D594381','ApeCoin','APE','ethereum',0.62,2.10,420000000,3100000,18000000,17520,9000,8500,1.06,90000,18.00,55,1,1,62,'MEDIUM','[\"Whale owns >18% of supply\"]',0),
('0xnewmeme00001demotoken0000000000000000001','TurboCat','TCAT','bsc',0.0000045,210.00,120000,9500,180000,3.5,1200,400,3.00,800,42.00,28,0,0,22,'HIGH','[\"Liquidity is not locked\",\"Whale owns >40% of supply\",\"Owner can mint new tokens\"]',0),
('0xnewmeme00002demotoken0000000000000000002','MoonDoge','MDOGE','base',0.00012,58.00,560000,42000,310000,12.0,3400,1500,2.27,2100,22.00,40,1,1,54,'MEDIUM','[\"Whale owns >22% of supply\"]',0),
('0xnewmeme00003demotoken0000000000000000003','PolyPepe','PPEPE','polygon',0.0000091,15.00,230000,18000,95000,30.0,900,700,1.29,1500,30.00,35,0,0,33,'HIGH','[\"Liquidity is not locked\"]',0)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Smart money demo data
INSERT INTO `smart_money`
(`wallet`,`label`,`token_symbol`,`token_contract`,`chain`,`amount_usd`,`est_profit`,`action`,`tx_date`) VALUES
('0x1f9090aaE28b8a3dCeaDf281B0F12828e676c326','Whale #1','PEPE','0x6982508145454ce325ddbe47a25d4ec3d2311933','ethereum',250000,182000,'buy', NOW() - INTERVAL 2 HOUR),
('0x28C6c06298d514Db089934071355E5743bf21d60','Smart Trader','WIF','EKpQGSJtjMFqKZ9KQanSqYXRcF8fBopzLHYxdM65zcjm','solana',88000,41000,'buy', NOW() - INTERVAL 5 HOUR),
('0x267be1C1D684F78cb4F6a176C4911b741E4Ffdc0','Early Bird','BONK','DezXAZ8z7PnrnRJjz3wXBoRgixCa6xjnB7YaB1pPB263','solana',32000,15500,'buy', NOW() - INTERVAL 9 HOUR),
('0xDFd5293D8e347dFe59E90eFd55b2956a1343963d','Whale #2','SHIB','0x95ad61b0a150d79219dcf64e1e6cc01f0b64c4ce','ethereum',410000,-22000,'sell', NOW() - INTERVAL 1 DAY),
('0x73AF3bcf944a6559933396c1577B257e2054D935','Insider','TCAT','0xnewmeme00001demotoken0000000000000000001','bsc',12000,9000,'buy', NOW() - INTERVAL 3 HOUR);

-- Sample banner / ad placeholders
INSERT INTO `banners` (`title`,`position`,`image`,`link`,`is_active`) VALUES
('Welcome Hero','hero','','#',1);

INSERT INTO `ads` (`title`,`placement`,`code`,`is_active`) VALUES
('Sample Sidebar Ad','sidebar','<div style=\"padding:20px;text-align:center;color:#8a90a2\">Your Ad Here</div>',0);
