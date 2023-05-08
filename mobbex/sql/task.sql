CREATE TABLE IF NOT EXISTS `DB_PREFIX_mobbex_task` (
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