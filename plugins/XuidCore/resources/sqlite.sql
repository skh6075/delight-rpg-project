-- #!sqlite
-- #{ xuidcore
-- #  { init
CREATE TABLE IF NOT EXISTS xuid_map (
    xuid TEXT PRIMARY KEY,
    player_name TEXT NOT NULL,
    player_name_lower TEXT NOT NULL,
    nickname TEXT,
    nickname_lower TEXT,
    last_updated INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_player_name_lower ON xuid_map(player_name_lower);
CREATE INDEX IF NOT EXISTS idx_nickname_lower ON xuid_map(nickname_lower);
-- #  }
-- #  { save
-- #    :xuid string
-- #    :player_name string
-- #    :player_name_lower string
-- #    :last_updated int
INSERT OR REPLACE INTO xuid_map (xuid, player_name, player_name_lower, last_updated) VALUES (:xuid, :player_name, :player_name_lower, :last_updated);
-- #  }
-- #  { nickname.set
-- #    :xuid string
-- #    :nickname string
-- #    :nickname_lower string
UPDATE xuid_map SET nickname = :nickname, nickname_lower = :nickname_lower WHERE xuid = :xuid;
-- #  }
-- #  { nickname.remove
-- #    :xuid string
UPDATE xuid_map SET nickname = NULL, nickname_lower = NULL WHERE xuid = :xuid;
-- #  }
-- #  { lookup.xuid.nickname
-- #    :nickname_lower string
SELECT xuid FROM xuid_map WHERE nickname_lower = :nickname_lower;
-- #  }
-- #  { lookup.xuid.name
-- #    :player_name_lower string
SELECT xuid FROM xuid_map WHERE player_name_lower = :player_name_lower;
-- #  }
-- #  { lookup.name
-- #    :xuid string
SELECT player_name FROM xuid_map WHERE xuid = :xuid;
-- #  }
-- #  { lookup.nickname
-- #    :xuid string
SELECT nickname FROM xuid_map WHERE xuid = :xuid;
-- #  }
-- #}
