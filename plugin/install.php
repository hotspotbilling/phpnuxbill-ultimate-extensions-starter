<?php

/**
 *  PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *  by https://t.me/ibnux
 **/

// Runs when the plugin is first installed and every time it is activated, so keep it idempotent.
// Plugins create their own tables; there are no migrations.
$db = ORM::getDb();
$exists = $db->query("SHOW TABLES LIKE 'tbl_myplugin_items'")->fetch();
if (!$exists) {
    $db->exec("CREATE TABLE `tbl_myplugin_items` (
        `id` int NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `created_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}
