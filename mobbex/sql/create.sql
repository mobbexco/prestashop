CREATE TABLE IF NOT EXISTS DB_PREFIX_mobbex_transaction (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `cart_id` INT(11) NOT NULL,
    `parent` TEXT NOT NULL,
    `payment_id` TEXT NOT NULL,
    `description` TEXT NOT NULL,
    `status_code` TEXT NOT NULL,
    `status` TEXT NOT NULL,
    `status_message` TEXT NOT NULL,
    `source_name` TEXT NOT NULL,
    `source_type` TEXT NOT NULL,
    `source_reference` TEXT NOT NULL,
    `source_number` TEXT NOT NULL,
    `source_expiration` TEXT NOT NULL,
    `source_installment` TEXT NOT NULL,
    `installment_name` TEXT NOT NULL,
    `source_url` TEXT NOT NULL,
    `cardholder` TEXT NOT NULL,
    `entity_name` TEXT NOT NULL,
    `entity_uid` TEXT NOT NULL,
    `customer` TEXT NOT NULL,
    `checkout_uid` TEXT NOT NULL,
    `total` DECIMAL(18,2) NOT NULL,
    `currency` TEXT NOT NULL,
    `risk_analysis` TEXT NOT NULL,
    `data` TEXT NOT NULL,
    `created` TEXT NOT NULL,
    `updated` TEXT NOT NULL
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS DB_PREFIX_mobbex_custom_fields (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `row_id` INT(11) NOT NULL,
    `object` TEXT NOT NULL,
    `field_name` TEXT NOT NULL,
    `data` TEXT NOT NULL
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS DB_PREFIX_mobbex_task (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` TEXT NOT NULL,
    `args` TEXT NOT NULL,
    `interval` INT(11) NOT NULL,
    `period` TEXT NOT NULL,
    `limit` INT(11) NOT NULL,
    `executions` INT(11) NOT NULL,
    `start_date` DATETIME NOT NULL,
    `last_execution` DATETIME NOT NULL,
    `next_execution` DATETIME NOT NULL
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;