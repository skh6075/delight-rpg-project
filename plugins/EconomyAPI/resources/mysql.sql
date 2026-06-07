-- #!mysql
-- # { init
CREATE TABLE IF NOT EXISTS economy_currency (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(32) NOT NULL,
    `symbol` VARCHAR(16) DEFAULT NULL,
    `default_balance` INT NOT NULL DEFAULT 0,
    `can_transaction` TINYINT(1) NOT NULL DEFAULT 0,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
-- #&
CREATE TABLE IF NOT EXISTS economy_balance (
    `xuid` VARCHAR(36) NOT NULL,
    `currency_id` INT NOT NULL,
    `balance` BIGINT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`xuid`, `currency_id`),
    FOREIGN KEY (`xuid`) REFERENCES xuid_map(`xuid`) ON DELETE CASCADE,
    FOREIGN KEY (`currency_id`) REFERENCES economy_currency(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
-- # }
-- # { load_currencies
SELECT * FROM economy_currency ORDER BY `id` ASC;
-- # }
-- # { load_balances
-- #   :xuid string
SELECT `currency_id`,`balance` FROM economy_balance WHERE `xuid` = :xuid ORDER BY `currency_id` ASC;
-- # }
-- # { load_balance
-- #   :xuid string
-- #   :currency_id int
SELECT * FROM economy_balance WHERE `xuid` = :xuid AND `currency_id` = :currency_id;
-- # }
-- # { create_currency
-- #   :name string
-- #   :symbol string
-- #   :default_balance int
-- #   :can_transaction int
-- #   :is_default int
INSERT INTO economy_currency (`name`,`symbol`, `default_balance`, `can_transaction`, `is_default`)
VALUES (:name, :symbol, :default_balance, :can_transaction, :is_default);
-- # }
-- # { delete_currency
-- #   :id int
DELETE FROM economy_currency WHERE `id` = :id;
-- # }
-- # { update_currency
-- #   :id int
-- #   :symbol string
-- #   :default_balance int
-- #   :can_transaction int
-- #   :is_default int
UPDATE economy_currency SET `symbol` = :symbol, `default_balance` = :default_balance, `can_transaction` = :can_transaction, `is_default` = :is_default WHERE `id` = :id;
-- # }
-- # { save_balance
-- #   :xuid string
-- #   :currency_id int
-- #   :balance int
INSERT INTO economy_balance (`xuid`, `currency_id`, `balance`)
VALUES (:xuid, :currency_id, :balance)
ON DUPLICATE KEY UPDATE `balance` = :balance;
-- # }
-- # { top_balance
-- #   :currency_id int
-- #   :limit int
-- #   :offset int
SELECT `xuid`, `balance` FROM economy_balance WHERE `currency_id` = :currency_id ORDER BY `balance` DESC LIMIT :limit OFFSET :offset;
-- # }