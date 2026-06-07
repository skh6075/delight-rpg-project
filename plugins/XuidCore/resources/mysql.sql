-- #!mysql
-- #{ xuidcore
-- #  { init
CREATE TABLE IF NOT EXISTS xuid_map (
    `xuid` VARCHAR(36) PRIMARY KEY,
    `player_name` VARCHAR(16) NOT NULL,
    `player_name_lower` VARCHAR(16) NOT NULL,
    `nickname` VARCHAR(16),
    `nickname_lower` VARCHAR(16),
    `last_updated` BIGINT NOT NULL,
    INDEX idx_player_name_lower (`player_name_lower`),
    INDEX idx_nickname_lower (`nickname_lower`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
-- #  }
-- #  { save
-- #    :xuid string
-- #    :player_name string
-- #    :player_name_lower string
-- #    :last_updated int
INSERT INTO xuid_map (`xuid`, `player_name`, `player_name_lower`, `last_updated`)
VALUES (:xuid, :player_name, :player_name_lower, :last_updated)
ON DUPLICATE KEY UPDATE `player_name` = :player_name, `player_name_lower` = :player_name_lower, `last_updated` = :last_updated;
-- #  }
-- #  { nickname.set
-- #    :xuid string
-- #    :nickname string
-- #    :nickname_lower string
UPDATE xuid_map SET `nickname` = :nickname, `nickname_lower` = :nickname_lower WHERE `xuid` = :xuid;
-- #  }
-- #  { nickname.remove
-- #    :xuid string
UPDATE xuid_map SET `nickname` = NULL, `nickname_lower` = NULL WHERE `xuid` = :xuid;
-- #  }
-- #  { lookup.xuid.nickname
-- #    :nickname_lower string
SELECT xuid FROM xuid_map WHERE `nickname_lower` = :nickname_lower;
-- #  }
-- #  { lookup.xuid.name
-- #    :player_name_lower string
SELECT xuid FROM xuid_map WHERE `player_name_lower` = :player_name_lower;
-- #  }
-- #  { lookup.name
-- #    :xuid string
SELECT player_name FROM xuid_map WHERE `xuid` = :xuid;
-- #  }
-- #  { lookup.nickname
-- #    :xuid string
SELECT nickname FROM xuid_map WHERE `xuid` = :xuid;
-- #  }
-- #}
