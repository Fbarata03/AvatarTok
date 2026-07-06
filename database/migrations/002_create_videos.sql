-- AvatarTok — Migration 002: Videos, sounds, effects

CREATE TABLE videos (
    id              CHAR(36)        NOT NULL PRIMARY KEY,
    author_id       CHAR(36)        NOT NULL,
    title           VARCHAR(150)    NULL,
    description     VARCHAR(2000)   NULL,
    -- S3 storage
    s3_key          VARCHAR(500)    NOT NULL,
    hls_key         VARCHAR(500)    NULL,     -- processed HLS playlist
    thumbnail_key   VARCHAR(500)    NULL,
    -- Metadata
    duration_sec    SMALLINT UNSIGNED NULL,
    width           SMALLINT UNSIGNED NULL,
    height          SMALLINT UNSIGNED NULL,
    file_size_bytes INT UNSIGNED    NULL,
    -- Avatar + effects
    sound_id        CHAR(36)        NULL,
    effect_ids      JSON            NULL,     -- applied effects
    avatar_recorded TINYINT(1)      NOT NULL DEFAULT 1,
    face_track_data MEDIUMBLOB      NULL,     -- compressed landmark data for replay
    -- Taxonomy
    category        VARCHAR(50)     NULL,
    hashtags        JSON            NULL,
    country         CHAR(2)         NULL,
    language        CHAR(5)         NULL,
    -- Engagement counters (denormalized for feed query speed)
    view_count      INT UNSIGNED    NOT NULL DEFAULT 0,
    like_count      INT UNSIGNED    NOT NULL DEFAULT 0,
    comment_count   INT UNSIGNED    NOT NULL DEFAULT 0,
    share_count     INT UNSIGNED    NOT NULL DEFAULT 0,
    -- Privacy & settings
    status          ENUM('processing','public','private','friends_only','removed','draft') NOT NULL DEFAULT 'processing',
    privacy         ENUM('public','private','friends_only') NOT NULL DEFAULT 'public',
    allow_duet      TINYINT(1)      NOT NULL DEFAULT 1,
    allow_stitch    TINYINT(1)      NOT NULL DEFAULT 1,
    allow_comments  TINYINT(1)      NOT NULL DEFAULT 1,
    -- Duet / Stitch references
    duet_of         CHAR(36)        NULL,
    stitch_of       CHAR(36)        NULL,
    stitch_clip_start FLOAT         NULL,
    stitch_clip_end   FLOAT         NULL,
    -- Moderation
    moderation_status ENUM('pending','approved','flagged','removed') NOT NULL DEFAULT 'pending',
    ai_score        FLOAT           NULL,
    removed_at      DATETIME        NULL,
    -- Timestamps
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_author     (author_id),
    INDEX idx_status     (status),
    INDEX idx_created    (created_at),
    INDEX idx_category   (category),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE video_uploads (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    user_id     CHAR(36)    NOT NULL,
    s3_key      VARCHAR(500) NOT NULL,
    status      ENUM('pending','uploaded','processing','complete','failed') NOT NULL DEFAULT 'pending',
    metadata    JSON        NULL,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE video_likes (
    user_id    CHAR(36) NOT NULL,
    video_id   CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, video_id),
    INDEX idx_video (video_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comments (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    video_id    CHAR(36)    NOT NULL,
    user_id     CHAR(36)    NOT NULL,
    parent_id   CHAR(36)    NULL,
    text        VARCHAR(500) NOT NULL,
    like_count  INT UNSIGNED NOT NULL DEFAULT 0,
    status      ENUM('visible','removed') NOT NULL DEFAULT 'visible',
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_video    (video_id),
    INDEX idx_parent   (parent_id),
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE watch_history (
    id              CHAR(36)    NOT NULL PRIMARY KEY,
    user_id         CHAR(36)    NOT NULL,
    video_id        CHAR(36)    NOT NULL,
    completion_pct  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    replayed        TINYINT(1)  NOT NULL DEFAULT 0,
    source          VARCHAR(30) NULL,   -- feed|following|search|profile|direct
    feed_id         CHAR(36)    NULL,
    watched_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_time  (user_id, watched_at),
    INDEX idx_video      (video_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Sounds ────────────────────────────────────────────────────────────────────

CREATE TABLE sounds (
    id              CHAR(36)        NOT NULL PRIMARY KEY,
    author_id       CHAR(36)        NULL,     -- NULL = platform original
    title           VARCHAR(200)    NOT NULL,
    artist          VARCHAR(100)    NULL,
    duration_sec    SMALLINT UNSIGNED NOT NULL,
    s3_key          VARCHAR(500)    NOT NULL,
    waveform_key    VARCHAR(500)    NULL,
    cover_key       VARCHAR(500)    NULL,
    category        VARCHAR(50)     NULL,
    bpm             SMALLINT UNSIGNED NULL,
    video_count     INT UNSIGNED    NOT NULL DEFAULT 0,
    status          ENUM('active','removed','copyright_claim') NOT NULL DEFAULT 'active',
    is_original     TINYINT(1)      NOT NULL DEFAULT 0,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sound_favorites (
    user_id    CHAR(36) NOT NULL,
    sound_id   CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, sound_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (sound_id) REFERENCES sounds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Effects / Filters ─────────────────────────────────────────────────────────

CREATE TABLE effects (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    category    VARCHAR(50) NOT NULL,   -- beauty|funny|ar|avatar|background
    preview_url VARCHAR(500) NULL,
    asset_url   VARCHAR(500) NOT NULL,
    shader_url  VARCHAR(500) NULL,      -- GLSL shader bundle for real-time render
    thumbnail   VARCHAR(500) NULL,
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    requires_face_track TINYINT(1) NOT NULL DEFAULT 0,
    active      TINYINT(1)  NOT NULL DEFAULT 1,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Reports ───────────────────────────────────────────────────────────────────

CREATE TABLE reports (
    id                CHAR(36) NOT NULL PRIMARY KEY,
    reporter_id       CHAR(36) NOT NULL,
    reported_user_id  CHAR(36) NULL,
    content_id        CHAR(36) NULL,
    content_type      ENUM('video','comment','sound','avatar','live_stream','chat') NOT NULL,
    reason            VARCHAR(100) NOT NULL,
    details           VARCHAR(500) NULL,
    status            ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
    action            VARCHAR(50) NULL,
    reviewer_id       CHAR(36) NULL,
    notes             VARCHAR(1000) NULL,
    reviewed_at       DATETIME NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_content (content_id, content_type),
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
