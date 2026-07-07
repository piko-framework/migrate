<?php

return new class
{
    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS `post` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `category` VARCHAR(50) NOT NULL,
          `ref_id` INT UNSIGNED DEFAULT NULL,
          `type` VARCHAR(50) NOT NULL,
          `name` VARCHAR(50) NOT NULL DEFAULT '',
          `caption` VARCHAR(255) DEFAULT NULL,
          `path` VARCHAR(255) NOT NULL DEFAULT '',
          `data` BLOB DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME DEFAULT NULL,
          PRIMARY KEY  (`id`),
          KEY `idx_media_lookup` (`category`, `ref_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS `post`');
    }
};
