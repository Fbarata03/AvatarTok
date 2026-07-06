-- AvatarTok — Migration 001: Core user tables

CREATE TABLE users (
    id                  CHAR(36)        NOT NULL PRIMARY KEY,
    username            VARCHAR(30)     NOT NULL UNIQUE,
    email               VARCHAR(255)    NOT NULL UNIQUE,
    password_hash       VARCHAR(255)    NOT NULL,
    display_name        VARCHAR(60)     NULL,
    bio                 VARCHAR(500)    NULL,
    birthdate           DATE            NOT NULL,
    country             CHAR(2)         NOT NULL,
    role                ENUM('user','creator','moderator','admin','super_admin') NOT NULL DEFAULT 'user',
    status              ENUM('active','suspended','banned','deactivated') NOT NULL DEFAULT 'active',
    trust_score         TINYINT UNSIGNED NOT NULL DEFAULT 50,  -- 0-100
    stripe_customer_id  VARCHAR(64)     NULL UNIQUE,
    email_verified_at   DATETIME        NULL,
    phone               VARCHAR(20)     NULL,
    phone_verified_at   DATETIME        NULL,
    suspended_until     DATETIME        NULL,
    last_login_at       DATETIME        NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email       (email),
    INDEX idx_username    (username),
    INDEX idx_status      (status),
    INDEX idx_country     (country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Avatars ──────────────────────────────────────────────────────────────────

CREATE TABLE avatars (
    id              CHAR(36)    NOT NULL PRIMARY KEY,
    user_id         CHAR(36)    NOT NULL UNIQUE,
    base_model      VARCHAR(50) NOT NULL DEFAULT 'default_v1',
    skin_tone       VARCHAR(20) NOT NULL,
    hair_style      VARCHAR(50) NOT NULL,
    hair_color      VARCHAR(20) NOT NULL,
    eye_shape       VARCHAR(30) NOT NULL,
    face_shape      VARCHAR(30) NOT NULL,
    body_type       VARCHAR(30) NOT NULL,
    accessories     JSON        NULL,     -- array of accessory IDs
    outfit          JSON        NULL,
    expression_pack VARCHAR(50) NULL DEFAULT 'standard',
    voice_filter    VARCHAR(50) NULL,
    avatar_url      VARCHAR(500) NULL,    -- rendered thumbnail URL
    calibrated_at   DATETIME    NULL,     -- last facial calibration
    created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avatar_accessories (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    category    VARCHAR(50) NOT NULL,   -- hat|glasses|earrings|outfit|background
    rarity      ENUM('common','rare','epic','legendary') NOT NULL DEFAULT 'common',
    coin_cost   INT UNSIGNED NOT NULL DEFAULT 0,
    preview_url VARCHAR(500) NULL,
    asset_url   VARCHAR(500) NULL,
    active      TINYINT(1)  NOT NULL DEFAULT 1,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_accessories (
    user_id       CHAR(36) NOT NULL,
    accessory_id  CHAR(36) NOT NULL,
    unlocked_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, accessory_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (accessory_id) REFERENCES avatar_accessories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Auth & security ───────────────────────────────────────────────────────────

CREATE TABLE refresh_tokens (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    user_id     CHAR(36)    NOT NULL,
    expires_at  DATETIME    NOT NULL,
    revoked_at  DATETIME    NULL,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_verifications (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    user_id     CHAR(36)    NOT NULL,
    token       VARCHAR(128) NOT NULL UNIQUE,
    expires_at  DATETIME    NOT NULL,
    used_at     DATETIME    NULL,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE phone_verifications (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    user_id     CHAR(36)    NOT NULL,
    code        CHAR(6)     NOT NULL,
    expires_at  DATETIME    NOT NULL,
    used_at     DATETIME    NULL,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    user_id     CHAR(36)    NOT NULL,
    token_hash  CHAR(64)    NOT NULL UNIQUE,
    expires_at  DATETIME    NOT NULL,
    used_at     DATETIME    NULL,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    email       VARCHAR(255) NOT NULL,
    ip_address  VARCHAR(45) NOT NULL,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_time (email, created_at),
    INDEX idx_ip_time    (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
