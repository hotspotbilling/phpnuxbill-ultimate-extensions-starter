<?php

/**
 *  PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *  by https://t.me/ibnux
 *
 *  My Device - starter device driver (folder style)
 *
 *  A driver is a plain class. The class name MUST equal the folder / file name ("MyDevice") because the
 *  billing engine calls (new $plan['device'])->method(...). It is created with no constructor arguments.
 *  Pick it for a plan in Services > Hotspot/PPPOE/VPN plan > Device.
 *
 *  $customer is a tbl_customers row, $plan is a tbl_user_recharges row joined with its plan fields
 *  (username, routers, name_plan, price, ...), $router_name is the router/device name stored on the plan.
 *  Throw nothing from here unless you want the whole request to fail; return values are only used by
 *  online_customer() (truthy = online).
 *
 *  WHAT THIS FILE SHOWS (search for the section number):
 *   1. Licensing (optional)   register_license(..., 'device') + "license_provider" in device.json
 *   2. The methods            what the billing engine calls, and when
 *   3. Talking to a device    info(), getClient() with the bundled RouterOS API client
 *
 *  Everything the core already gives you:
 *   ORM::for_table('tbl_routers')   the router / device rows (Network > Routers): name, ip_address, username, password
 *   $_app_stage                     "Demo" means do not touch real equipment (the core drivers return early)
 *   _log($text, $type, $userId)     the Logs page;  Lang::T('text') translate
 *   PEAR2\Net\RouterOS              the RouterOS API client the Mikrotik drivers use (Client, Request, Query)
 *   Http::getData() / postJsonData()  for equipment that has an HTTP API
 **/

// ---------------------------------------------------------------------------------------------------------------------
// 1. LICENSING (optional). Leave $mydevice_licensed false for a free driver.
//
// To sell it: set "license_provider": true in device.json (the core then includes this file to find the rules) and flip the flag
// below. The core shows a License button on the driver's Marketplace card and does not let plans use the driver until your status
// function says "valid". Do NOT set "requires_license" in device.json as well. See PLUGINS.md > "Licensing a paid extension".
// ---------------------------------------------------------------------------------------------------------------------
$mydevice_licensed = false;
if ($mydevice_licensed) {
    register_license('MyDevice', 'mydevice_license_status', 'mydevice_license_save', 'device');
}

/** Cheap and offline: ['state' => 'valid'|'invalid'|'none', 'message' => '...', 'features' => []] */
function mydevice_license_status()
{
    global $config;
    $key = trim((string) ($config['mydevice_license_key'] ?? ''));
    if ($key === '') {
        return ['state' => 'none', 'message' => Lang::T('Enter your licence key')];
    }
    // verify the key your own way (see the plugin skeleton: myplugin_license_verify()); this placeholder accepts any key
    return ['state' => 'valid', 'message' => Lang::T('Licensed')];
}

/** The admin typed a key under Marketplace > Device Drivers > this driver > License */
function mydevice_license_save($key)
{
    global $config;
    $key = trim($key);
    $d = ORM::for_table('tbl_appconfig')->where('setting', 'mydevice_license_key')->find_one();
    if (!$d) {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'mydevice_license_key';
    }
    $d->value = $key;
    $d->save();
    $config['mydevice_license_key'] = $key;
    return mydevice_license_status();
}

// ---------------------------------------------------------------------------------------------------------------------
// 2. THE METHODS the billing engine calls. Leave the ones you do not need empty rather than deleting them.
// ---------------------------------------------------------------------------------------------------------------------
class MyDevice
{
    /** Shown in Settings > Devices and in the Marketplace */
    public function description()
    {
        return [
            'title' => 'My Device',
            'description' => 'Describe what this driver controls.',
            'author' => 'Your Name',
            'url' => [
                'Github' => 'https://github.com/your/repo',
                'Telegram' => 'https://t.me/yourchannel',
            ],
        ];
    }

    /** A customer buys / is activated on a plan: create or enable their account on the device */
    public function add_customer($customer, $plan)
    {
        $device = $this->info($plan['routers']);
        // e.g. call the device API with $customer['username'], $customer['password'], $plan['name_plan'] ...
    }

    /** Plan expired or customer removed: disable / delete the account on the device */
    public function remove_customer($customer, $plan)
    {
    }

    /**
     * Optional. Settings > Sync: re-send an active customer to the device (for example after the device was reset). When this
     * method does not exist the core calls add_customer() instead.
     */
    public function sync_customer($customer, $plan)
    {
        $this->add_customer($customer, $plan);
    }

    /** The customer's username was changed: rename the account on the device */
    public function change_username($plan, $from, $to)
    {
    }

    /** A plan was created in the admin: create the matching profile / queue / rate limit */
    public function add_plan($plan)
    {
    }

    /** A plan was edited ($old_name is the previous profile name) */
    public function update_plan($old_name, $plan)
    {
    }

    /** A plan was deleted */
    public function remove_plan($plan)
    {
    }

    /** Is the customer connected right now? Return true/false (or a session id) */
    public function online_customer($customer, $router_name)
    {
        return false;
    }

    /** Hotspot login page: connect this customer from $ip / $mac_address now */
    public function connect_customer($customer, $ip, $mac_address, $router_name)
    {
    }

    /** Hotspot logout: disconnect the customer now */
    public function disconnect_customer($customer, $router_name)
    {
    }

    // ---- optional: only needed by plan types that use IP pools (PPPOE) ---------------------------
    // public function add_pool($pool) {}
    // public function update_pool($old_pool, $new_pool) {}
    // public function remove_pool($pool) {}

    // ---------------------------------------------------------------------------------------------------------------------
    // 3. TALKING TO A DEVICE
    // ---------------------------------------------------------------------------------------------------------------------

    /** The router / device row (tbl_routers) configured under Network > Routers: ip_address, username, password, ... */
    public function info($name)
    {
        return ORM::for_table('tbl_routers')->where('name', $name)->find_one();
    }

    /**
     * A RouterOS API connection, the way the Mikrotik drivers open one. In Demo mode it returns null so nothing real is touched.
     *
     *   $device = $this->info($plan['routers']);
     *   $client = $this->getClient($device['ip_address'], $device['username'], $device['password']);
     *   if ($client) {
     *       $client->sendSync(
     *           (new PEAR2\Net\RouterOS\Request('/ip/hotspot/user/add'))
     *               ->setArgument('name', $customer['username'])
     *               ->setArgument('password', $customer['password'])
     *               ->setArgument('profile', $plan['name_plan'])
     *       );
     *   }
     * Equipment with an HTTP API: Http::postJsonData($url, $body, ['Authorization: Bearer ...']).
     */
    public function getClient($ip, $user, $pass, $port = null, $timeout = 8)
    {
        global $_app_stage;
        if ($_app_stage == 'Demo') {
            return null;
        }
        $hostPort = explode(':', $ip);
        $p = $port ?: ($hostPort[1] ?? null);
        return new PEAR2\Net\RouterOS\Client($hostPort[0], $user, $pass, $p, false, $timeout);
    }
}
