<?php

/**
 *  PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *  by https://t.me/ibnux
 *
 *  My Plugin - starter plugin (folder style)
 *
 *  Loaded on every request while the plugin is active. Keep this file light: register things here and define
 *  functions, do the real work inside the page functions below.
 *
 *  WHAT THIS FILE SHOWS (search for the section number):
 *   1. Licensing (optional)   register_license(), license_has()
 *   2. Menus in code          register_menu(), register_submenu()  (the same menus can live in plugin.json)
 *   3. Routes                 register_route()                     (the same routes can live in plugin.json)
 *   4. Hooks                  register_hook(), run_hook()
 *   5. Admin pages            _admin(), Admin::_info(), Csrf, Paginator, getUrl(), r2(), _post(), _log(), Lang::T()
 *   6. A customer page        _auth(), User::_info()
 *   7. A public webhook       verify the caller yourself
 *   8. A JSON endpoint        showResult() so HTML and system/api.php both work
 *   9. Cron                   the cronjob_end hook, Message::sendTelegram()
 *
 *  Everything the core already gives you, so you do not write it again:
 *   ORM::for_table()     database (Idiorm; binds values, never build SQL from user input)
 *   $config / $_c        settings from tbl_appconfig (PHP / templates)
 *   Lang::T('text')      translation; Lang::moneyFormat(), Lang::dateTimeFormat()
 *   _post() _get() _req() trimmed input (they do NOT escape)
 *   Csrf::generateAndStoreToken() / Csrf::check()
 *   r2($url, 's'|'e'|'w', $msg)  redirect with a notice (JSON in api mode); showResult() for JSON
 *   getUrl('path&a=b')   a link that honours the site's Pretty URL setting. Never write U . 'path'
 *   pluginAssetUrl('myplugin')   public URL of this plugin's assets/ folder
 *   Message::sendTelegram() sendSMS() sendWhatsapp() sendEmail()   notifications
 **/

// Helpers in includes/ are plain PHP files; include_once what you need.
include_once __DIR__ . '/includes/helpers.php';

// ---------------------------------------------------------------------------------------------------------------------
// 1. LICENSING (optional). Leave $myplugin_licensed false for a free plugin.
//
// To sell the plugin set it to true. You decide the rules: myplugin_license_status() answers "is this site licensed and for
// what", myplugin_license_save($key) takes the key an admin types under Marketplace > the plugin's card > License. The core only
// shows the button, the badge and your message. See PLUGINS.md > "Licensing a paid extension".
//
// Do NOT also set "requires_license" in plugin.json: that stops the plugin loading, so it could never register here.
// ---------------------------------------------------------------------------------------------------------------------
$myplugin_licensed = false;

if ($myplugin_licensed) {
    register_license('myplugin', 'myplugin_license_status', 'myplugin_license_save');

    // A plugin is always loaded (that is how it registers), so it must guard itself. Stop here, before any menu, route or
    // hook is registered, when the licence is missing. Repeat license_has() in anything sensitive: webhooks, cron, saves.
    // Functions below are still defined (PHP defines top-level functions before running the file), but nothing points at them.
    if (!license_has('myplugin', 'licensed')) {
        return;
    }
}

/** The licence state. Called often, so keep it cheap and offline (no network here). */
function myplugin_license_status()
{
    $key = myplugin_setting('myplugin_license_key', '');
    if ($key === '') {
        return ['state' => 'none', 'message' => Lang::T('Enter your licence key')];
    }
    $info = myplugin_license_verify($key);
    if ($info === null) {
        return ['state' => 'invalid', 'message' => Lang::T('This key is not valid for this site')];
    }
    // 'features' is what this key unlocks: ask with license_has('myplugin', 'reports'), or put "feature": "reports"
    // on a menu in plugin.json to hide that menu without it. 'licensed' always means "any valid licence".
    return ['state' => 'valid', 'message' => Lang::T('Licensed'), 'features' => $info['features']];
}

/** The admin pressed License and typed a key. Store it (wherever you like) and answer with the result. */
function myplugin_license_save($key)
{
    $key = trim($key);
    myplugin_save_setting('myplugin_license_key', $key);
    return $key === '' ? ['state' => 'none', 'message' => Lang::T('Licence removed')] : myplugin_license_status();
}

/**
 * Check a key offline: the key is  base64(json payload) . '.' . base64(Ed25519 signature)  made with YOUR private key.
 * Only the public key is in this file. The payload is {"domain": "shop.example.com", "features": ["reports"], "expires": 0}
 * (expires is a Unix time, 0 = never). A key cannot be forged and does not work on another domain. Needs PHP's sodium.
 * Returns the payload, or null when the key is not valid for this site.
 */
function myplugin_license_verify($key)
{
    $publicKey = ''; // base64 of your 32-byte Ed25519 public key. Empty means nothing is ever valid.
    $parts = explode('.', $key, 2);
    if ($publicKey === '' || count($parts) !== 2 || !function_exists('sodium_crypto_sign_verify_detached')) {
        return null;
    }
    $payload = base64_decode($parts[0], true);
    $sig = base64_decode($parts[1], true);
    $pk = base64_decode($publicKey, true);
    $data = $payload === false ? null : json_decode($payload, true);
    if ($sig === false || $pk === false || !is_array($data) || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES || strlen($pk) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
        return null;
    }
    if (!sodium_crypto_sign_verify_detached($sig, $payload, $pk)) {
        return null;
    }
    $host = strtolower((string) parse_url(APP_URL, PHP_URL_HOST));
    if (strtolower((string) ($data['domain'] ?? '')) !== $host || (!empty($data['expires']) && (int) $data['expires'] < time())) {
        return null;
    }
    return ['features' => is_array($data['features'] ?? null) ? $data['features'] : []];
}

// ---------------------------------------------------------------------------------------------------------------------
// 2. MENUS IN CODE. The menus of this skeleton are declared in plugin.json ("menus"); this is the same thing in PHP, which an
// encoder such as ionCube / SourceGuardian can protect. Declare each menu in ONE place, not both. Remove the "menus" list from
// plugin.json when you switch.
//
//   register_menu($name, $admin, $function, $position, $icon, $label, $color, $auth)
//   register_submenu($parentFunction, $name, $admin, $function, $position, $icon, $label, $color, $auth)
//
// Mind the order: skipping $label / $color and passing the $auth array in their place prints "Array" in the menu and leaves it
// open to every role. Pass '' and 'success' to skip them. $admin true = staff sidebar, false = customer portal; a submenu needs the
// same $position and $admin as its parent. $auth is a list of user types, [] means everyone. $label is a small badge.
//
//   register_menu('My Plugin', true, 'myplugin', 'AFTER_DASHBOARD', 'ion ion-cube', '', 'success', ['SuperAdmin', 'Admin']);
//   register_submenu('myplugin', 'Overview', true, 'myplugin/overview', 'AFTER_DASHBOARD', '', '', 'success', ['SuperAdmin', 'Admin']);
//
// A menu that only a licence tier sees (needs section 1 turned on):
//   if (license_has('myplugin', 'reports')) {
//       register_submenu('myplugin', 'Reports', true, 'myplugin/reports', 'AFTER_DASHBOARD', '', 'Pro', 'info', ['SuperAdmin', 'Admin']);
//   }
// In plugin.json the same gate is  "feature": "reports"  on the menu entry.
// ---------------------------------------------------------------------------------------------------------------------

// ---------------------------------------------------------------------------------------------------------------------
// 3. ROUTES IN CODE. Anything a URL may call that is not a menu page (a webhook, an AJAX endpoint, your own API) must be
// declared, or enforce mode refuses it. plugin.json ("routes") does the same. Declare each route once.
//
//   register_route('myplugin_webhook', 'public');      // anyone: YOU verify the caller
//   register_route('myplugin_ajax_items', 'admin');    // a signed-in staff member, checked by the core first
//   register_route('myplugin_customer_data', 'customer');
// ---------------------------------------------------------------------------------------------------------------------

// ---------------------------------------------------------------------------------------------------------------------
// 4. HOOKS. Core code calls run_hook('name') at fixed moments; a plugin listens with register_hook('name', 'function').
// Find names with:  grep -rn "run_hook(" system/
// Handlers take no arguments and must not print anything or exit; read the request through $_POST / globals if needed.
// ---------------------------------------------------------------------------------------------------------------------
register_hook('cronjob_end', 'myplugin_cron');                    // end of system/cron.php, every minute or so
register_hook('customer_buy_plan', 'myplugin_on_customer_buy');   // a customer started buying a package

// You can offer hooks of your own so other plugins can extend yours:  run_hook('myplugin_item_saved');  (see add_item below)

/** Example listener: write a line to Logs when a customer starts buying. Keep hook handlers fast. */
function myplugin_on_customer_buy()
{
    _log('[myplugin] a customer is buying a package', 'Customer');
}

// ---------------------------------------------------------------------------------------------------------------------
// 5. ADMIN PAGES. Route: ?_route=plugin/myplugin/<section>  (pretty URL: /plugin/myplugin/<section>)
// The menu entries call this function with "myplugin/overview" and "myplugin/settings"; what follows the first slash arrives
// in $routes[2], the next part in $routes[3].
// ---------------------------------------------------------------------------------------------------------------------
function myplugin()
{
    global $ui, $routes;
    _admin();                                    // sign-in check, and it makes the staff menu show
    $admin = Admin::_info();
    // roles: SuperAdmin, Admin, Report, Agent, Sales, Asset, Support
    if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
        _alert(Lang::T('You do not have permission to access this page'), 'danger', 'dashboard');
    }
    $ui->assign('_admin', $admin);
    $ui->assign('_system_menu', '');             // highlights a sidebar entry, '' = none
    $ui->assign('csrf_token', Csrf::generateAndStoreToken());
    $ui->assign('myplugin_assets', pluginAssetUrl('myplugin'));

    $section = $routes[2] ?? 'overview';
    switch ($section) {
        case 'settings':
            myplugin_settings($admin);
            break;
        case 'add':
            myplugin_add_item($admin);
            break;
        case 'delete':
            myplugin_delete_item($admin, (int) ($routes[3] ?? 0));
            break;
        default:
            myplugin_overview();
    }
}

function myplugin_overview()
{
    global $ui;
    $ui->assign('_title', Lang::T('My Plugin'));
    // Paginator reads ?p=, assigns what pagination.tpl needs and returns one page of rows (arrays when the last argument is true).
    // Filters go in the query first: ->where('col', $value) binds the value.
    $query = ORM::for_table('tbl_myplugin_items')->order_by_desc('id');
    $ui->assign('items', Paginator::findMany($query, [], 10, '', true) ?: []);
    run_hook('view_myplugin_overview');          // lets other plugins add to your page: they listen with register_hook()
    $ui->display('myplugin.tpl');                // ui/myplugin.tpl - folder plugins resolve templates by plain name
}

function myplugin_settings($admin)
{
    global $ui;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::check(_post('csrf_token'))) {
            r2(getUrl('plugin/myplugin/settings'), 'e', Lang::T('Invalid or Expired CSRF Token') . '.');
        }
        myplugin_save_setting('myplugin_greeting', _post('myplugin_greeting'));
        myplugin_save_setting('myplugin_enabled', _post('myplugin_enabled') === 'yes' ? 'yes' : 'no');
        // _log($description, $type, $userId): shows under Logs. $type is free text; the admin's user_type is conventional.
        _log('[' . $admin['username'] . '] My Plugin settings saved', 'Admin', $admin['id']);
        r2(getUrl('plugin/myplugin/settings'), 's', Lang::T('Settings Saved Successfully')); // 's' success, 'e' error, 'w' warning
    }
    $ui->assign('_title', Lang::T('My Plugin') . ' - ' . Lang::T('Settings'));
    $ui->display('myplugin_settings.tpl');
}

function myplugin_add_item($admin)
{
    // Every action that changes data: POST only, and a CSRF token. r2() ends the request.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::check(_post('csrf_token'))) {
        r2(getUrl('plugin/myplugin/overview'), 'e', Lang::T('Invalid or Expired CSRF Token') . '.');
    }
    $title = trim(_post('title'));
    if ($title === '') {
        r2(getUrl('plugin/myplugin/overview'), 'e', Lang::T('Title is required'));
    }
    $d = ORM::for_table('tbl_myplugin_items')->create();
    $d->title = $title;                          // Idiorm binds values; never concatenate user input into SQL
    $d->created_at = date('Y-m-d H:i:s');
    $d->save();
    run_hook('myplugin_item_saved');             // your own hook (see section 4)
    r2(getUrl('plugin/myplugin/overview'), 's', Lang::T('Saved'));
}

function myplugin_delete_item($admin, $id)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::check(_post('csrf_token'))) {
        r2(getUrl('plugin/myplugin/overview'), 'e', Lang::T('Invalid or Expired CSRF Token') . '.');
    }
    $d = ORM::for_table('tbl_myplugin_items')->find_one($id);
    if ($d) {
        $d->delete();
    }
    r2(getUrl('plugin/myplugin/overview'), 's', Lang::T('Deleted'));
}

// ---------------------------------------------------------------------------------------------------------------------
// 6. A CUSTOMER PAGE (a menu with "admin": false). Route: ?_route=plugin/myplugin_customer
// ---------------------------------------------------------------------------------------------------------------------
function myplugin_customer()
{
    global $ui, $config;
    _auth();                                     // customer sign-in check; it also makes the customer menu show
    $user = User::_info();                       // the signed-in customer's row (id, username, fullname, balance ...)
    $ui->assign('_title', Lang::T('My Plugin'));
    $ui->assign('_user', $user);
    $ui->assign('greeting', myplugin_setting('myplugin_greeting', 'Hello'));
    // Lang::moneyFormat($user['balance']) formats an amount with the site's currency settings; use it in templates too.
    $ui->display('myplugin_customer.tpl');
}

// ---------------------------------------------------------------------------------------------------------------------
// 7. A PUBLIC WEBHOOK. Declared in plugin.json ("routes") with access "public", or register_route() in section 3.
// Route: ?_route=plugin/myplugin_webhook
//
// A public route is called by strangers: verify who is calling (a signature or a secret) before you trust anything, and never
// echo input back unescaped. A webhook cannot send a CSRF token, so verify its signature instead.
// ---------------------------------------------------------------------------------------------------------------------
function myplugin_webhook()
{
    $secret = myplugin_setting('myplugin_webhook_secret', '');
    $given = (string) ($_SERVER['HTTP_X_MYPLUGIN_TOKEN'] ?? '');
    if ($secret === '' || !hash_equals($secret, $given)) {
        http_response_code(401);
        exit;
    }
    $payload = json_decode((string) file_get_contents('php://input'), true);
    // ... do something with $payload. Make it idempotent: the same event may arrive twice. ...
    http_response_code(200);
    exit;
}

// ---------------------------------------------------------------------------------------------------------------------
// 8. A JSON ENDPOINT for scripts on your page (an "admin" route in plugin.json: the core checks the sign-in first).
// Route: ?_route=plugin/myplugin_ajax_items
//
// Answer with showResult($success, $message, $result): it prints JSON and stops. Never die('text'): the same code also runs
// behind system/api.php, where a bare string breaks the JSON.
// ---------------------------------------------------------------------------------------------------------------------
function myplugin_ajax_items()
{
    _admin();
    $q = _req('q');                              // _req reads GET or POST, trimmed
    $query = ORM::for_table('tbl_myplugin_items')->order_by_desc('id')->limit(20);
    if ($q !== '') {
        $query->where_like('title', '%' . $q . '%');   // where_like binds the value too
    }
    showResult(true, '', $query->find_array());
}

// ---------------------------------------------------------------------------------------------------------------------
// 9. CRON. Runs at the end of system/cron.php (cronjob_end hook, section 4).
// ---------------------------------------------------------------------------------------------------------------------
function myplugin_cron()
{
    if (myplugin_setting('myplugin_enabled', 'no') !== 'yes') {
        return;
    }
    // do periodic work here. Heavy work should be skipped unless it is due: this runs on every cron tick.
    // Notifications go through the core, which already knows the admin's settings:
    //   Message::sendTelegram('My Plugin: something happened');
    //   Message::sendSMS($phone, $text);  Message::sendWhatsapp($phone, $text);  Message::sendEmail($to, $subject, $body);
}
