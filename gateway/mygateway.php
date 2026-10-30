<?php

/**
 *  PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *  by https://t.me/ibnux
 *
 *  My Gateway - starter payment gateway (folder style)
 *
 *  Rules of the gateway API:
 *   - the file is included on demand (checkout, the settings page, the webhook), NOT on every request: it cannot
 *     register menus or hooks the way a plugin does. Everything here is a function the core calls by name.
 *   - every function is prefixed with the slug ("mygateway_"); the slug is the folder name, letters / digits / underscore
 *   - configuration lives in tbl_appconfig under "<slug>_*" keys ($config holds every row)
 *   - tbl_payment_gateway.status: 1 unpaid, 2 paid, 3 failed/expired, 4 cancelled
 *
 *  Flow: customer picks the gateway -> order.php includes this file, calls mygateway_validate_config()
 *  then mygateway_create_transaction() -> customer pays on the provider -> provider calls the webhook
 *  (mygateway_payment_notification) and/or the customer lands on order/view/<id>/check which calls
 *  mygateway_get_status().
 *
 *  WHAT THIS FILE SHOWS (search for the section number):
 *   1. Licensing (optional)      register_license(..., 'gateway') + "license_provider" in gateway.json
 *   2. Provider settings         sandbox / live switch through $_app_stage
 *   3. The six contract functions validate_config, show_config, save_config, create_transaction, get_status, payment_notification
 *   4. Settling an order         Package::rechargeUser(), idempotent
 *
 *  Everything the core already gives you:
 *   ORM::for_table('tbl_payment_gateway')   the transaction row ($trx); ORM::for_table('tbl_customers') for the customer
 *   Http::getData() / Http::postData() / Http::postJsonData()   calls to the provider (return the raw body)
 *   getUrl('order/view/5/check')  a link that honours Pretty URL. Never write U . 'path'
 *   r2($url, 's'|'e'|'w'|'d', $msg)  redirect with a notice;  Lang::T() translate;  Lang::moneyFormat($amount)
 *   Csrf::check(_post('csrf_token'))  on every admin form;  _log($text, 'Admin', $admin['id'])  the Logs page
 *   sendTelegram($text)  tells the admin something went wrong (needs the Telegram settings)
 *   Message::sendTelegram() sendSMS() sendWhatsapp() sendEmail()  notifications to anyone else
 *
 *  The $trx row (tbl_payment_gateway): id, username, user_id, gateway, gateway_trx_id, plan_id, plan_name, routers,
 *  routers_id, price, pg_url_payment, pg_request, pg_paid_response, expired_date, created_date, paid_date,
 *  payment_method, payment_channel, trx_invoice, status. The $user row (tbl_customers): id, username, fullname, email, phonenumber, balance.
 **/

// ---------------------------------------------------------------------------------------------------------------------
// 1. LICENSING (optional). Leave $mygateway_licensed false for a free gateway.
//
// To sell it: set "license_provider": true in gateway.json (so the core includes this file to find the rules) and flip the flag
// below. You decide how a key looks and where it is checked; the core only shows the License button on the gateway's card in
// Marketplace, and refuses to use the gateway (checkout, status check, webhook) until your status function says "valid".
// Do NOT set "requires_license" in gateway.json at the same time. See PLUGINS.md > "Licensing a paid extension".
// ---------------------------------------------------------------------------------------------------------------------
$mygateway_licensed = false;
if ($mygateway_licensed) {
    register_license('mygateway', 'mygateway_license_status', 'mygateway_license_save', 'gateway');
}

/** The licence state; cheap and offline. Return ['state' => 'valid'|'invalid'|'none', 'message' => '...', 'features' => []] */
function mygateway_license_status()
{
    global $config;
    $key = trim((string) ($config['mygateway_license_key'] ?? ''));
    if ($key === '') {
        return ['state' => 'none', 'message' => Lang::T('Enter your licence key')];
    }
    // Check the key however you decide, ideally offline against a signature that only your private key can make
    // (see the plugin skeleton: myplugin_license_verify()). Here: any non-empty key passes, replace this.
    return ['state' => 'valid', 'message' => Lang::T('Licensed')];
}

/** The admin typed a key under Marketplace > this gateway > License */
function mygateway_license_save($key)
{
    global $config;
    $key = trim($key);
    $d = ORM::for_table('tbl_appconfig')->where('setting', 'mygateway_license_key')->find_one();
    if (!$d) {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'mygateway_license_key';
    }
    $d->value = $key;
    $d->save();
    $config['mygateway_license_key'] = $key;
    return mygateway_license_status();
}

// ---------------------------------------------------------------------------------------------------------------------
// 2. PROVIDER SETTINGS: replace with the real API
// ---------------------------------------------------------------------------------------------------------------------
defined('MYGATEWAY_LIVE_API') || define('MYGATEWAY_LIVE_API', 'https://api.example-pay.com/v1/');
defined('MYGATEWAY_TEST_API') || define('MYGATEWAY_TEST_API', 'https://sandbox.example-pay.com/v1/');

/** Sandbox until the site's stage is "Live" ($_app_stage comes from config.php) */
function mygateway_api_base()
{
    global $_app_stage;
    return $_app_stage == 'Live' ? MYGATEWAY_LIVE_API : MYGATEWAY_TEST_API;
}

// ---------------------------------------------------------------------------------------------------------------------
// 3. THE SIX FUNCTIONS THE CORE CALLS
// ---------------------------------------------------------------------------------------------------------------------

/** Called before every checkout: stop early (with a friendly message) when the admin has not configured the gateway */
function mygateway_validate_config()
{
    global $config;
    if (empty($config['mygateway_api_key']) || empty($config['mygateway_secret'])) {
        sendTelegram('My Gateway payment gateway is not configured');
        r2(getUrl('order/package'), 'w', Lang::T('Admin has not yet setup this payment gateway, please tell admin'));
    }
}

/** Admin: Payment Gateway > My Gateway (GET) */
function mygateway_show_config()
{
    global $ui;
    $ui->assign('_title', 'My Gateway - ' . Lang::T('Payment Gateway'));
    $ui->assign('csrf_token', Csrf::generateAndStoreToken());
    $ui->assign('webhook_url', getUrl('callback/mygateway'));  // the address to paste in the provider dashboard
    $ui->display('mygateway.tpl');          // ui/mygateway.tpl ("pg:mygateway.tpl" also works)
}

/** Admin: save settings (POST) */
function mygateway_save_config()
{
    global $admin, $config;
    if (!Csrf::check(_post('csrf_token'))) {
        r2(getUrl('paymentgateway/mygateway'), 'e', Lang::T('Invalid or Expired CSRF Token') . '.');
    }
    foreach (['mygateway_api_key', 'mygateway_secret', 'mygateway_currency'] as $key) {
        $d = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_one();
        if (!$d) {
            $d = ORM::for_table('tbl_appconfig')->create();
            $d->setting = $key;
        }
        $d->value = trim(_post($key));       // _post() only trims: it does not escape. Idiorm binds the value on save.
        $d->save();
        $config[$key] = $d->value;
    }
    _log('[' . $admin['username'] . ']: My Gateway ' . Lang::T('Settings Saved Successfully'), 'Admin', $admin['id']);
    r2(getUrl('paymentgateway/mygateway'), 's', Lang::T('Settings Saved Successfully'));
}

/**
 * Customer chose to pay: create the payment on the provider and send the browser to its checkout page.
 * @param ORM  $trx  the tbl_payment_gateway row that was just created (status 1)
 * @param ORM  $user the customer
 */
function mygateway_create_transaction($trx, $user)
{
    global $config;
    $payload = [
        'reference' => 'PNB-' . $trx['id'],
        'amount' => (string) $trx['price'],
        'currency' => ($config['mygateway_currency'] ?? '') ?: 'USD',
        'customer' => ['name' => $user['fullname'], 'email' => $user['email'], 'phone' => $user['phonenumber']],
        'description' => $trx['plan_name'],
        // the customer comes back here, and order/view/<id>/check runs mygateway_get_status()
        'return_url' => getUrl('order/view/' . $trx['id'] . '/check'),
        'cancel_url' => getUrl('order/view/' . $trx['id']),
        'webhook_url' => getUrl('callback/mygateway'),
    ];
    // Http::postJsonData($url, $body, $headers) sends JSON and returns the raw response; Http::postData() sends a form
    $result = json_decode(Http::postJsonData(
        mygateway_api_base() . 'checkout',
        $payload,
        ['Authorization: Bearer ' . $config['mygateway_api_key']]
    ), true);

    if (empty($result['id']) || empty($result['checkout_url'])) {
        sendTelegram("mygateway_create_transaction FAILED:\n\n" . json_encode($result, JSON_PRETTY_PRINT));
        r2(getUrl('order/package'), 'e', Lang::T('Failed to create transaction.'));
    }

    // Keep what the provider answered: gateway_trx_id is how the webhook finds this row again
    $trx->gateway_trx_id = $result['id'];
    $trx->pg_url_payment = $result['checkout_url'];
    $trx->pg_request = json_encode($result);
    $trx->expired_date = date('Y-m-d H:i:s', strtotime('+6 hour'));
    $trx->save();

    header('Location: ' . $result['checkout_url']);
    exit();
}

/**
 * Customer returned from the provider or pressed "check payment": ask the provider and settle the order.
 * Must be idempotent - it can run many times for one transaction.
 */
function mygateway_get_status($trx, $user)
{
    global $config;
    // Http::getData($url, $headers) is a GET and returns the raw response
    $result = json_decode(Http::getData(
        mygateway_api_base() . 'checkout/' . rawurlencode($trx['gateway_trx_id']),
        ['Authorization: Bearer ' . $config['mygateway_api_key']]
    ), true);

    $status = $result['status'] ?? '';
    if ($status === 'paid' && $trx['status'] != 2) {
        mygateway_mark_paid($trx, $user, $result);
        r2(getUrl('order/view/' . $trx['id']), 's', Lang::T('Transaction has been paid.'));
    } elseif (in_array($status, ['failed', 'expired'], true)) {
        $trx->pg_paid_response = json_encode($result);
        $trx->status = 3;
        $trx->save();
        r2(getUrl('order/view/' . $trx['id']), 'd', Lang::T('Transaction expired.'));
    } elseif ($trx['status'] == 2) {
        r2(getUrl('order/view/' . $trx['id']), 's', Lang::T('Transaction has been paid.'));
    }
    r2(getUrl('order/view/' . $trx['id']), 'w', Lang::T('Transaction status') . ': ' . ($status ?: 'unknown'));
}

/**
 * Provider webhook: https://your-site/callback/mygateway  (route callback/<slug>, no sign-in: anyone can call it)
 * Verify the signature BEFORE trusting anything in the body. A webhook cannot send a CSRF token, so the signature is its proof.
 */
function mygateway_payment_notification()
{
    global $config;
    $raw = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $secret = (string) ($config['mygateway_secret'] ?? '');
    // never accept a webhook while no secret is configured: an empty HMAC key is trivially forgeable
    if ($secret === '' || $signature === '') {
        header('HTTP/1.1 401 Unauthorized');
        die('invalid signature');
    }
    $expected = hash_hmac('sha256', $raw, $secret);
    if (!hash_equals($expected, $signature)) {
        header('HTTP/1.1 401 Unauthorized');
        die('invalid signature');
    }
    $event = json_decode($raw, true);
    if (empty($event['id']) || ($event['status'] ?? '') !== 'paid') {
        die('OK'); // acknowledge events we do not act on
    }
    $trx = ORM::for_table('tbl_payment_gateway')
        ->where('gateway', 'mygateway')
        ->where('gateway_trx_id', $event['id'])
        ->find_one();
    if ($trx && $trx['status'] != 2) {
        $user = ORM::for_table('tbl_customers')->find_one($trx['user_id']);
        if ($user) {
            mygateway_mark_paid($trx, $user, $event);
        }
    }
    die('OK');
}

// ---------------------------------------------------------------------------------------------------------------------
// 4. SETTLING AN ORDER (shared by polling and the webhook)
// ---------------------------------------------------------------------------------------------------------------------

/** Activate the plan and close the transaction. Callers check "status != 2" first, so a repeat does nothing. */
function mygateway_mark_paid($trx, $user, $providerResponse)
{
    // Package::rechargeUser($customerId, $router, $planId, $gateway, $channel) activates the plan, writes the invoice
    // (tbl_transactions) and notifies the customer. It returns false when the device could not be reached.
    if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'My Gateway')) {
        r2(getUrl('order/view/' . $trx['id']), 'd', Lang::T('Failed to activate your Package, try again later.'));
    }
    $trx->pg_paid_response = json_encode($providerResponse);
    $trx->payment_method = 'MYGATEWAY';
    $trx->payment_channel = 'mygateway';
    $trx->paid_date = date('Y-m-d H:i:s');
    $trx->status = 2;
    $trx->save();
}
