-- AvatarTok — Migration 003: Social graph, live streaming, chat, monetization

-- ── Social graph ──────────────────────────────────────────────────────────────

CREATE TABLE follows (
    follower_id   CHAR(36) NOT NULL,
    following_id  CHAR(36) NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, following_id),
    INDEX idx_following (following_id),
    FOREIGN KEY (follower_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blocks (
    blocker_id  CHAR(36) NOT NULL,
    blocked_id  CHAR(36) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (blocker_id, blocked_id),
    FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    user_id     CHAR(36)    NOT NULL,
    type        VARCHAR(50) NOT NULL,   -- like|comment|follow|gift|mention|live_start
    actor_id    CHAR(36)    NULL,
    entity_id   CHAR(36)    NULL,
    entity_type VARCHAR(30) NULL,
    payload     JSON        NULL,
    read_at     DATETIME    NULL,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_unread (user_id, read_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE push_tokens (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    user_id     CHAR(36)    NOT NULL,
    platform    ENUM('ios','android','web') NOT NULL,
    token       VARCHAR(512) NOT NULL,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Chat ──────────────────────────────────────────────────────────────────────

CREATE TABLE conversations (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    type        ENUM('direct','group') NOT NULL DEFAULT 'direct',
    name        VARCHAR(100) NULL,     -- group name
    created_by  CHAR(36)    NOT NULL,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conversation_members (
    conversation_id CHAR(36) NOT NULL,
    user_id         CHAR(36) NOT NULL,
    role            ENUM('member','admin') NOT NULL DEFAULT 'member',
    last_read_at    DATETIME NULL,
    joined_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (conversation_id, user_id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
    id              CHAR(36)    NOT NULL PRIMARY KEY,
    conversation_id CHAR(36)    NOT NULL,
    sender_id       CHAR(36)    NOT NULL,
    type            ENUM('text','image','video','gift','sticker','audio') NOT NULL DEFAULT 'text',
    text            VARCHAR(2000) NULL,
    media_url       VARCHAR(500) NULL,
    reply_to_id     CHAR(36)    NULL,
    reactions       JSON        NULL,   -- {"❤️": ["user_id1", ...], ...}
    deleted_at      DATETIME    NULL,
    created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conversation (conversation_id, created_at),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id)       REFERENCES users(id)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Live streaming ────────────────────────────────────────────────────────────

CREATE TABLE live_streams (
    id              CHAR(36)        NOT NULL PRIMARY KEY,
    user_id         CHAR(36)        NOT NULL,
    title           VARCHAR(100)    NOT NULL,
    description     VARCHAR(500)    NULL,
    category        VARCHAR(50)     NULL,
    rtmp_key        VARCHAR(128)    NOT NULL UNIQUE,
    hls_url         VARCHAR(500)    NULL,
    status          ENUM('scheduled','live','ended') NOT NULL DEFAULT 'live',
    allow_gifts     TINYINT(1)      NOT NULL DEFAULT 1,
    min_gift_coins  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    viewer_count    INT UNSIGNED    NOT NULL DEFAULT 0,
    peak_viewers    INT UNSIGNED    NOT NULL DEFAULT 0,
    total_viewers   INT UNSIGNED    NOT NULL DEFAULT 0,
    started_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at        DATETIME        NULL,
    replay_key      VARCHAR(500)    NULL,
    scheduled_at    DATETIME        NULL,
    INDEX idx_user   (user_id),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE live_co_hosts (
    stream_id   CHAR(36) NOT NULL,
    user_id     CHAR(36) NOT NULL,
    status      ENUM('invited','accepted','declined','ended') NOT NULL DEFAULT 'invited',
    joined_at   DATETIME NULL,
    PRIMARY KEY (stream_id, user_id),
    FOREIGN KEY (stream_id) REFERENCES live_streams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Monetization ─────────────────────────────────────────────────────────────

CREATE TABLE wallets (
    user_id         CHAR(36)    NOT NULL PRIMARY KEY,
    balance         INT UNSIGNED NOT NULL DEFAULT 0,       -- spendable coins
    pending_balance INT UNSIGNED NOT NULL DEFAULT 0,       -- gift earnings awaiting payout
    total_earned    INT UNSIGNED NOT NULL DEFAULT 0,
    total_spent     INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gifts (
    id              CHAR(36)    NOT NULL PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    description     VARCHAR(300) NULL,
    coin_cost       INT UNSIGNED NOT NULL,
    animation_key   VARCHAR(100) NOT NULL,  -- client-side animation identifier
    icon_url        VARCHAR(500) NULL,
    rarity          ENUM('common','rare','epic','legendary') NOT NULL DEFAULT 'common',
    active          TINYINT(1)  NOT NULL DEFAULT 1,
    created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gift_transactions (
    id              CHAR(36)    NOT NULL PRIMARY KEY,
    sender_id       CHAR(36)    NOT NULL,
    receiver_id     CHAR(36)    NOT NULL,
    stream_id       CHAR(36)    NULL,
    video_id        CHAR(36)    NULL,
    gift_id         CHAR(36)    NOT NULL,
    quantity        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    coins_total     INT UNSIGNED NOT NULL,
    platform_cut    INT UNSIGNED NOT NULL,
    creator_cut     INT UNSIGNED NOT NULL,
    created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sender   (sender_id),
    INDEX idx_receiver (receiver_id),
    FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (gift_id)     REFERENCES gifts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transactions (
    id                  CHAR(36)    NOT NULL PRIMARY KEY,
    user_id             CHAR(36)    NOT NULL,
    type                VARCHAR(50) NOT NULL,  -- coin_purchase|gift_sent|gift_received|payout|ad_revenue|subscription
    amount_cents        INT         NULL,       -- fiat amount (if applicable)
    currency            CHAR(3)     NULL,
    coins               INT         NULL,       -- coin delta (positive=credit, negative=debit)
    stripe_intent_id    VARCHAR(128) NULL,
    status              ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
    completed_at        DATETIME    NULL,
    created_at          DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_type (user_id, type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_methods (
    id                          CHAR(36)    NOT NULL PRIMARY KEY,
    user_id                     CHAR(36)    NOT NULL,
    stripe_payment_method_id    VARCHAR(64) NOT NULL UNIQUE,
    type                        VARCHAR(20) NOT NULL,
    last4                       CHAR(4)     NULL,
    brand                       VARCHAR(20) NULL,
    exp_month                   TINYINT     NULL,
    exp_year                    SMALLINT    NULL,
    is_default                  TINYINT(1)  NOT NULL DEFAULT 0,
    created_at                  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payout_requests (
    id                  CHAR(36)    NOT NULL PRIMARY KEY,
    user_id             CHAR(36)    NOT NULL,
    amount_coins        INT UNSIGNED NOT NULL,
    amount_usd_cents    INT UNSIGNED NOT NULL,
    fee_usd_cents       INT UNSIGNED NOT NULL DEFAULT 0,
    method              VARCHAR(30) NOT NULL,
    account_id          VARCHAR(128) NOT NULL,
    stripe_payout_id    VARCHAR(64) NULL,
    status              ENUM('pending','approved','processing','paid','rejected') NOT NULL DEFAULT 'pending',
    estimated_arrival   DATE        NULL,
    rejected_reason     VARCHAR(300) NULL,
    paid_at             DATETIME    NULL,
    created_at          DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user   (user_id),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
    id                      CHAR(36)    NOT NULL PRIMARY KEY,
    user_id                 CHAR(36)    NOT NULL,
    plan_id                 VARCHAR(50) NOT NULL,
    stripe_subscription_id  VARCHAR(64) NOT NULL UNIQUE,
    status                  VARCHAR(20) NOT NULL,
    current_period_end      DATETIME    NULL,
    cancel_at_period_end    TINYINT(1)  NOT NULL DEFAULT 0,
    cancelled_at            DATETIME    NULL,
    created_at              DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Ads ───────────────────────────────────────────────────────────────────────

CREATE TABLE ad_campaigns (
    id              CHAR(36)    NOT NULL PRIMARY KEY,
    advertiser_id   CHAR(36)    NOT NULL,
    name            VARCHAR(200) NOT NULL,
    budget_cents    INT UNSIGNED NOT NULL,
    spent_cents     INT UNSIGNED NOT NULL DEFAULT 0,
    bid_cpm_cents   INT UNSIGNED NOT NULL,   -- cost per 1000 impressions
    target_country  CHAR(2)     NULL,
    target_category VARCHAR(50) NULL,
    target_age_min  TINYINT     NULL,
    target_age_max  TINYINT     NULL,
    creative_url    VARCHAR(500) NOT NULL,
    click_url       VARCHAR(500) NULL,
    status          ENUM('active','paused','completed','rejected') NOT NULL DEFAULT 'active',
    starts_at       DATETIME    NULL,
    ends_at         DATETIME    NULL,
    created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (advertiser_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ad_impressions (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    campaign_id CHAR(36)    NOT NULL,
    user_id     CHAR(36)    NULL,
    skipped     TINYINT(1)  NOT NULL DEFAULT 0,
    clicked     TINYINT(1)  NOT NULL DEFAULT 0,
    cost_cents  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_campaign (campaign_id, created_at),
    FOREIGN KEY (campaign_id) REFERENCES ad_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Moderation ────────────────────────────────────────────────────────────────

CREATE TABLE moderation_queue (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    content_id  CHAR(36)    NOT NULL,
    type        VARCHAR(20) NOT NULL,
    status      ENUM('pending','ai_flagged','reviewed','cleared') NOT NULL DEFAULT 'pending',
    ai_score    FLOAT       NULL,
    ai_labels   JSON        NULL,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE banned_words (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    word        VARCHAR(100) NOT NULL,
    language    VARCHAR(5)  NOT NULL DEFAULT 'en',
    severity    ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    category    VARCHAR(50) NOT NULL DEFAULT 'general',
    INDEX idx_lang_word (language, word)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE moderation_actions (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    admin_id    CHAR(36)    NOT NULL,
    target_id   CHAR(36)    NOT NULL,
    target_type VARCHAR(20) NOT NULL,
    action      VARCHAR(50) NOT NULL,
    reason      VARCHAR(500) NULL,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_warnings (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    user_id     CHAR(36)    NOT NULL,
    reason      VARCHAR(500) NOT NULL,
    message     VARCHAR(1000) NULL,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
