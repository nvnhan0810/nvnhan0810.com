CREATE TABLE foreign_youtube_channels (
 user_id UUID NOT NULL REFERENCES identity_users(id),
 channel_id TEXT NOT NULL,
 title TEXT NOT NULL,
 thumbnail_url TEXT NOT NULL,
 uploads_playlist_id TEXT NOT NULL,
 created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
 PRIMARY KEY (user_id, channel_id)
);
CREATE INDEX foreign_media_youtube_source ON foreign_media (user_id, (payload->>'source_id'))
 WHERE payload->>'type' = 'youtube';
