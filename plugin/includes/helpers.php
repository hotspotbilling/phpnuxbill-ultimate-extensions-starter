<?php

/**
 *  PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *  by https://t.me/ibnux
 **/

/**
 * Read a setting from tbl_appconfig ($config already holds every row, loaded before any page function runs).
 * Prefix your keys (myplugin_*) so they cannot clash with the core's or another plugin's.
 */
function myplugin_setting($key, $default = '')
{
    global $config;
    return isset($config[$key]) && $config[$key] !== '' ? $config[$key] : $default;
}

/**
 * Create or update a tbl_appconfig row and keep $config in step for the rest of this request.
 * tbl_appconfig is key / value: setting, value. Remove your keys in uninstall.php if the plugin should leave nothing behind.
 */
function myplugin_save_setting($key, $value)
{
    global $config;
    $d = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_one();
    if (!$d) {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = $key;
    }
    $d->value = $value;
    $d->save();
    $config[$key] = $value;
}
