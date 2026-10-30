# Payment gateway skeleton

A complete folder-style gateway: settings page, hosted-checkout redirect, status polling for the "check payment" button and
a signed webhook. The provider calls are placeholders (`api.example-pay.com`); replace them with your provider's API.

```
mygateway/
├── gateway.json       manifest
├── mygateway.php      the six gateway functions + helpers
├── ui/mygateway.tpl   admin settings page
└── README.md
```

## Make it yours

```bash
php skeletons/build.php new gateway acmepay "Acme Pay"
```

The slug becomes the folder and the function prefix (`acmepay_validate_config()` ...), so it must be lowercase letters,
digits and `_` and start with a letter.

## The gateway contract

The billing system includes your file on demand and calls these functions (all prefixed with the slug):

| Function | When | Job |
|---|---|---|
| `<slug>_validate_config()` | before every checkout | redirect with a message if the admin has not configured it |
| `<slug>_show_config()` | admin opens Payment Gateway > yours | assign template vars, `$ui->display('<slug>.tpl')` |
| `<slug>_save_config()` | admin saves (POST) | check CSRF, store `<slug>_*` rows in `tbl_appconfig` |
| `<slug>_create_transaction($trx, $user)` | customer chooses the gateway | create the payment at the provider, store `gateway_trx_id` + `pg_url_payment`, redirect the browser |
| `<slug>_get_status($trx, $user)` | customer returns / presses check | ask the provider, settle or fail the order, `r2()` back to `order/view/<id>` |
| `<slug>_payment_notification()` | provider webhook `callback/<slug>` | verify the signature, settle the order, answer `OK` |

`tbl_payment_gateway.status`: `1` unpaid, `2` paid, `3` failed or expired, `4` cancelled. Settle with
`Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'Channel name')` and then set
`status = 2`, `paid_date`, `pg_paid_response`. **Make settling idempotent**: polling and the webhook can both fire, and the
skeleton only recharges while `status != 2`.

## What `mygateway.php` demonstrates

| # | Section | APIs |
|---|---|---|
| 1 | Licensing (off by default) | `register_license(..., 'gateway')`, `"license_provider": true` in `gateway.json`, a status / save pair you control |
| 2 | Provider settings | sandbox vs live through `$_app_stage` |
| 3 | The six contract functions | `Http::postJsonData()` / `getData()`, `getUrl()`, `r2()`, `Csrf`, `_log()`, `sendTelegram()`, `Lang::T()` |
| 4 | Settling an order | `Package::rechargeUser()`, idempotent, the `tbl_payment_gateway` columns |

The header of the file lists every `$trx` column. Links go through `getUrl('order/view/5')`, never `U . '...'`, so they follow the site's
Pretty URL setting (the provider webhook address too).

A gateway file is included on demand, so unlike a plugin it cannot register menus or hooks.

## Licensing a paid gateway

Set `"license_provider": true` in `gateway.json` and `$mygateway_licensed = true` in the file. The core then includes the file
(once) to find your `register_license()`, shows a **License** button on the gateway's card in Marketplace, and does not use the gateway
for checkout, the status check or the webhook until `mygateway_license_status()` answers `valid`. The settings page still opens so the
admin can reach the button. Do not set `requires_license` as well. Only a folder gateway can do this: a one-file gateway has no manifest.

## Templates

`$ui->display('mygateway.tpl')` and `$ui->display('pg:mygateway.tpl')` both find `ui/mygateway.tpl`. Reading your own data
files? Use `__DIR__ . '/file.json'`, not paths relative to the web root.

## Webhook security

The skeleton answers `401` unless the request carries an `X-Signature` equal to the HMAC-SHA256 of the raw body with the
configured secret (compared with `hash_equals`), and it refuses every webhook while no secret is set. Adapt the header
name and algorithm to your provider.

## Going live

* Set the webhook URL shown on the settings page in the provider dashboard.
* `$_app_stage` decides sandbox vs live URLs (`Live` = live).
* Enable the gateway in Marketplace > Payment Gateways (**Enable**) or Payment Gateway settings. A gateway with a PHP error,
  unmet requirement or missing licence cannot be enabled.
* Add `"github"` / `"update_url"` and bump `"version"` when you ship fixes.
