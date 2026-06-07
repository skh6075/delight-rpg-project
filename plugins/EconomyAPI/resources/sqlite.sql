-- #!sqlite
-- #{ economyapi
-- #  { init
CREATE TABLE IF NOT EXISTS economy_currency (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    symbol TEXT DEFAULT NULL,
    default_balance INTEGER NOT NULL DEFAULT 0,
    can_transaction INTEGER NOT NULL DEFAULT 0,
    is_default INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL DEFAULT (unixepoch()),
    updated_at INTEGER NOT NULL DEFAULT (unixepoch())
);
-- #&
CREATE TABLE IF NOT EXISTS economy_balance (
    xuid TEXT NOT NULL,
    currency_id INTEGER NOT NULL,
    balance BIGINT NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL DEFAULT (unixepoch()),
    updated_at INTEGER NOT NULL DEFAULT (unixepoch()),
    PRIMARY KEY (xuid, currency_id),
    FOREIGN KEY (xuid) REFERENCES xuid_map(xuid) ON DELETE CASCADE,
    FOREIGN KEY (currency_id) REFERENCES economy_currency(id) ON DELETE CASCADE
);
-- #&
CREATE INDEX IF NOT EXISTS idx_economy_balance_xuid ON economy_balance(xuid);
-- #&
CREATE INDEX IF NOT EXISTS idx_economy_balance_currency_id ON economy_balance(currency_id);
-- #  }
-- #  { load_currencies
SELECT * FROM economy_currency ORDER BY id ASC;
-- #  }
-- #  { load_balances
-- #    :xuid string
SELECT currency_id, balance FROM economy_balance WHERE xuid = :xuid ORDER BY currency_id ASC;
-- #  }
-- #  { load_balance
-- #    :xuid string
-- #    :currency_id int
SELECT * FROM economy_balance WHERE xuid = :xuid AND currency_id = :currency_id;
-- #  }
-- #  { create_currency
-- #    :name string
-- #    :symbol string
-- #    :default_balance int
-- #    :can_transaction int
-- #    :is_default int
INSERT INTO economy_currency (name, symbol, default_balance, can_transaction, is_default, created_at, updated_at)
VALUES (:name, :symbol, :default_balance, :can_transaction, :is_default, unixepoch(), unixepoch());
-- #  }
-- #  { delete_currency
-- #    :id int
DELETE FROM economy_currency WHERE id = :id;
-- #  }
-- #  { update_currency
-- #    :id int
-- #    :symbol string
-- #    :default_balance int
-- #    :can_transaction int
-- #    :is_default int
UPDATE economy_currency
SET symbol = :symbol,
    default_balance = :default_balance,
    can_transaction = :can_transaction,
    is_default = :is_default,
    updated_at = unixepoch()
WHERE id = :id;
-- #  }
-- #  { save_balance
-- #    :xuid string
-- #    :currency_id int
-- #    :balance int
INSERT INTO economy_balance (xuid, currency_id, balance, created_at, updated_at)
VALUES (:xuid, :currency_id, :balance, unixepoch(), unixepoch())
ON CONFLICT(xuid, currency_id) DO UPDATE SET
    balance = excluded.balance,
    updated_at = unixepoch();
-- #  }
-- #  { top_balance
-- #    :currency_id int
-- #    :limit int
-- #    :offset int
SELECT xuid, balance FROM economy_balance WHERE currency_id = :currency_id ORDER BY balance DESC LIMIT :limit OFFSET :offset;
-- #  }
-- #}
