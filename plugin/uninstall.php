<?php

/**
 *  PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *  by https://t.me/ibnux
 **/

// Runs when the plugin is uninstalled from the Marketplace (the folder is backed up first).
// Dropping data is destructive: leave it commented out unless the plugin should wipe its tables.
// ORM::getDb()->exec("DROP TABLE IF EXISTS `tbl_myplugin_items`");
// ORM::for_table('tbl_appconfig')->where_like('setting', 'myplugin\_%')->delete_many();
