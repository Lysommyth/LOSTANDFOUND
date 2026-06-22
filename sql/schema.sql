
CREATE DATABASE IF NOT EXISTS campus_lost_found;
USE campus_lost_found;

-- ============================================================
-- 1. USERS
-- ============================================================
CREATE TABLE users (
    id                 INT(11)      NOT NULL AUTO_INCREMENT,
    username           VARCHAR(50)  NOT NULL,
    email              VARCHAR(100) NOT NULL,
    password           VARCHAR(255) NULL,                  -- hashed via password_hash()
    course_year        VARCHAR(100) NULL,                  -- e.g. "BSc IT Year 2"
    role               ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    verification_token VARCHAR(255) NULL,
    is_verified        TINYINT(1)   NOT NULL DEFAULT 0,
    created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE INDEX idx_email (email)
) ENGINE=InnoDB;

-- ============================================================
-- 2. ITEMS
-- ============================================================
CREATE TABLE items (
    item_id        INT          NOT NULL AUTO_INCREMENT,
    finder_id      INT          NOT NULL,                  -- FK → users.id
    item_name      VARCHAR(150) NOT NULL,
    category       ENUM(
                       'Electronics',
                       'Books',
                       'Clothing',
                       'Keys',
                       'IDs/Wallets',
                       'Bags',
                       'Accessories',
                       'Other'
                   ) NOT NULL,
    description    TEXT         NOT NULL,
    location_found VARCHAR(255) NOT NULL,                  -- e.g. "STC Cafeteria"
    status         ENUM('available', 'claimed', 'returned') NOT NULL DEFAULT 'available',
    image_path     VARCHAR(255) NULL,                      -- e.g. "uploads/item_abc123.jpg"
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id),
    FOREIGN KEY (finder_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 3. CLAIMS
-- ============================================================
CREATE TABLE claims (
    claim_id      INT      NOT NULL AUTO_INCREMENT,
    item_id       INT      NOT NULL,                       -- FK → items.item_id
    user_id       INT      NOT NULL,                       -- FK → users.id (the claimant)
    claim_details TEXT     NOT NULL,                       -- why this item is theirs
    phone_number  VARCHAR(20) NULL,                        -- required for admin-flow claims
    claim_status  ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    admin_note    TEXT     NULL,                           -- note left by admin or finder
    reviewed_at   TIMESTAMP NULL,                          -- when admin/finder acted on it
    claimed_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (claim_id),
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)      ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 4. NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
    notif_id   INT          NOT NULL AUTO_INCREMENT,
    user_id    INT          NOT NULL,                      -- FK → users.id (recipient)
    message    TEXT         NOT NULL,
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notif_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 5. PEER SESSIONS
--    Created when a finder approves a peer-to-peer claim
-- ============================================================
CREATE TABLE peer_sessions (
    session_id   INT      NOT NULL AUTO_INCREMENT,
    item_id      INT      NOT NULL,                        -- FK → items.item_id
    finder_id    INT      NOT NULL,                        -- FK → users.id
    claimant_id  INT      NOT NULL,                        -- FK → users.id
    claim_id     INT      NOT NULL,                        -- FK → claims.claim_id
    status       ENUM('active', 'completed') NOT NULL DEFAULT 'active',
    completed_at TIMESTAMP NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (session_id),
    FOREIGN KEY (item_id)     REFERENCES items(item_id)    ON DELETE CASCADE,
    FOREIGN KEY (finder_id)   REFERENCES users(id)         ON DELETE CASCADE,
    FOREIGN KEY (claimant_id) REFERENCES users(id)         ON DELETE CASCADE,
    FOREIGN KEY (claim_id)    REFERENCES claims(claim_id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 6. PEER MESSAGES
--    Chat messages within a peer session
-- ============================================================
CREATE TABLE peer_messages (
    message_id INT       NOT NULL AUTO_INCREMENT,
    session_id INT       NOT NULL,                         -- FK → peer_sessions.session_id
    sender_id  INT       NOT NULL,                         -- FK → users.id
    message    TEXT      NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id),
    FOREIGN KEY (session_id) REFERENCES peer_sessions(session_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id)  REFERENCES users(id)                 ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 7. EXCHANGE LOGS
--    One record per peer session — tracks confirmations & proof
-- ============================================================
CREATE TABLE exchange_logs (
    log_id                INT          NOT NULL AUTO_INCREMENT,
    session_id            INT          NOT NULL UNIQUE,    -- FK → peer_sessions.session_id
    item_id               INT          NOT NULL,           -- FK → items.item_id
    finder_id             INT          NOT NULL,           -- FK → users.id
    claimant_id           INT          NOT NULL,           -- FK → users.id
    finder_contact        VARCHAR(150) NULL,               -- email captured at session start
    claimant_contact      VARCHAR(150) NULL,               -- email captured at session start
    confirmed_by_claimant TINYINT(1)   NOT NULL DEFAULT 0,
    confirmed_by_finder   TINYINT(1)   NOT NULL DEFAULT 0,
    exchange_photo_path   VARCHAR(255) NULL,               -- optional proof photo
    exchange_notes        TEXT         NULL,               -- optional handover notes
    created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    FOREIGN KEY (session_id)   REFERENCES peer_sessions(session_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id)      REFERENCES items(item_id)            ON DELETE CASCADE,
    FOREIGN KEY (finder_id)    REFERENCES users(id)                 ON DELETE CASCADE,
    FOREIGN KEY (claimant_id)  REFERENCES users(id)                 ON DELETE CASCADE
) ENGINE=InnoDB;