CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(64) PRIMARY KEY,
    display_name VARCHAR(120) NOT NULL,
    normalized_display_name VARCHAR(120) NOT NULL,
    created_at VARCHAR(40) NOT NULL,
    UNIQUE KEY idx_users_normalized_display_name (normalized_display_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    connected TINYINT(1) NOT NULL DEFAULT 0,
    connected_at VARCHAR(40) NOT NULL,
    last_seen_at VARCHAR(40) NOT NULL,
    INDEX idx_sessions_user_id (user_id),
    INDEX idx_sessions_connected (connected),
    CONSTRAINT fk_sessions_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rooms (
    id VARCHAR(64) PRIMARY KEY,
    type VARCHAR(40) NOT NULL,
    name VARCHAR(120) NULL,
    created_by VARCHAR(64) NOT NULL,
    created_at VARCHAR(40) NOT NULL,
    INDEX idx_rooms_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS room_members (
    room_id VARCHAR(64) NOT NULL,
    user_id VARCHAR(64) NOT NULL,
    joined_at VARCHAR(40) NOT NULL,
    PRIMARY KEY (room_id, user_id),
    INDEX idx_room_members_user_id (user_id),
    CONSTRAINT fk_room_members_room_id FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id VARCHAR(64) PRIMARY KEY,
    room_id VARCHAR(64) NOT NULL,
    from_user_id VARCHAR(64) NOT NULL,
    kind VARCHAR(40) NOT NULL,
    body TEXT NULL,
    metadata_json JSON NOT NULL,
    created_at VARCHAR(40) NOT NULL,
    INDEX idx_messages_room_created (room_id, created_at),
    CONSTRAINT fk_messages_room_id FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attachments (
    id VARCHAR(64) PRIMARY KEY,
    message_id VARCHAR(64) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes BIGINT NOT NULL,
    path TEXT NOT NULL,
    created_at VARCHAR(40) NOT NULL,
    CONSTRAINT fk_attachments_message_id FOREIGN KEY (message_id) REFERENCES messages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
