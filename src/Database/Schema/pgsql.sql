CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(64) PRIMARY KEY,
    display_name VARCHAR(120) NOT NULL,
    normalized_display_name VARCHAR(120) NOT NULL UNIQUE,
    created_at VARCHAR(40) NOT NULL
);

CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    connected BOOLEAN NOT NULL DEFAULT FALSE,
    connected_at VARCHAR(40) NOT NULL,
    last_seen_at VARCHAR(40) NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_sessions_user_id
    ON sessions (user_id);

CREATE INDEX IF NOT EXISTS idx_sessions_connected
    ON sessions (connected);

CREATE TABLE IF NOT EXISTS rooms (
    id VARCHAR(64) PRIMARY KEY,
    type VARCHAR(40) NOT NULL,
    name VARCHAR(120) NULL,
    created_by VARCHAR(64) NOT NULL,
    created_at VARCHAR(40) NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_rooms_type
    ON rooms (type);

CREATE TABLE IF NOT EXISTS room_members (
    room_id VARCHAR(64) NOT NULL REFERENCES rooms (id) ON DELETE CASCADE,
    user_id VARCHAR(64) NOT NULL,
    joined_at VARCHAR(40) NOT NULL,
    PRIMARY KEY (room_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_room_members_user_id
    ON room_members (user_id);

CREATE TABLE IF NOT EXISTS messages (
    id VARCHAR(64) PRIMARY KEY,
    room_id VARCHAR(64) NOT NULL REFERENCES rooms (id) ON DELETE CASCADE,
    from_user_id VARCHAR(64) NOT NULL,
    kind VARCHAR(40) NOT NULL,
    body TEXT NULL,
    metadata_json JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at VARCHAR(40) NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_messages_room_created
    ON messages (room_id, created_at);

CREATE TABLE IF NOT EXISTS attachments (
    id VARCHAR(64) PRIMARY KEY,
    message_id VARCHAR(64) NOT NULL REFERENCES messages (id) ON DELETE CASCADE,
    filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes BIGINT NOT NULL,
    path TEXT NOT NULL,
    created_at VARCHAR(40) NOT NULL
);
