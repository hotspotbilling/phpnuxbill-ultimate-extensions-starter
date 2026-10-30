# Plugin skeleton

A complete folder-style plugin: admin pages (overview + settings), a customer page, a cron hook, a database table created
on install, and static assets.

```
myplugin/
├── plugin.json            manifest + sidebar menus
├── myplugin.php           loaded on every request while active: hooks, page functions
├── install.php            runs on install / activate (idempotent): create tables
├── uninstall.php          runs on uninstall: clean up (destructive lines are commented out)
├── includes/helpers.php   plain PHP helpers (settings read/write)
├── controllers/           optional: split big plugins into files and include_once them
├── ui/                    Smarty templates, resolved by plain name ($ui->display('myplugin.tpl'))
│   ├── myplugin.tpl            admin overview (list + add/delete)
│   ├── myplugin_settings.tpl   admin settings form
│   └── myplugin_customer.tpl   customer page
└── assets/css/myplugin.css  served at pluginAssetUrl('myplugin') . 'css/myplugin.css'
```

## What `myplugin.php` demonstrates

Read the numbered sections in the file; each one is commented to be copied.

| # | Section | APIs |
|---|---|---|
| 1 | Licensing (off by default) | `register_license()`, `license_has()`, an offline Ed25519 key check, licence features |
| 2 | Menus in code | `register_menu()`, `register_submenu()`, a licence-gated menu (the same menus can live in `plugin.json`) |
| 3 | Routes in code | `register_route()` with `public`, `admin`, `customer` (the same routes can live in `plugin.json`) |
| 4 | Hooks | `register_hook()` (`cronjob_end`, `customer_buy_plan`), `run_hook()` for hooks of your own |
| 5 | Admin pages | `_admin()`, `Admin::_info()`, roles, `Csrf`, `Paginator::findMany()`, `getUrl()`, `r2()`, `_post()`, `_log()`, `Lang::T()`, Idiorm |
| 6 | A customer page | `_auth()`, `User::_info()` |
| 7 | A public webhook | verifying the caller yourself |
| 8 | A JSON endpoint | `_req()`, `showResult()` |
| 9 | Cron | `Message::sendTelegram()` and friends |

Links go through `getUrl('plugin/myplugin/settings')`, never `U . '...'`, so they follow the site's Pretty URL setting.

## Make it yours

```bash
php skeletons/build.php new plugin acme_sms "Acme SMS"
```

renames the folder, `acme_sms.php`, every `myplugin_` function and `tbl_myplugin_items`, and the display names. Then edit
`plugin.json` (author, description, version, requirements).

## How it works

* **Menus** are declared in `plugin.json` (or in code, section 2 of `myplugin.php`: pick one place). `"type": "menu"` is a top-level entry, `"type": "submenu"` hangs under
  `"parent"` (the parent's `function`). `"admin": true` is the admin sidebar, `false` the customer menu (the two never mix:
  an admin menu cannot show in the customer portal even when both sessions are open). `position` is the sidebar slot the
  header template prints. Admin, between the built-in groups: `AFTER_DASHBOARD AFTER_CUSTOMERS AFTER_PLANS AFTER_SERVICES
  AFTER_NETWORKS AFTER_RADIUS AFTER_MESSAGE AFTER_REPORTS AFTER_PAGES AFTER_LOGS AFTER_SETTINGS AFTER_COMMUNITY`, or as an item
  inside a group: `PLANS SERVICES NETWORK RADIUS MESSAGE REPORTS PAGES LOGS SETTINGS MAPS`. Customer: `AFTER_DASHBOARD
  AFTER_INBOX AFTER_ORDER AFTER_HISTORY`. A submenu must use the same `position` (and the same `admin` value) as its parent.
  Call `_admin()` in admin pages and `_auth()` in customer pages: that is what picks the audience's menu. `auth` limits it to user types
  (`SuperAdmin`, `Admin`, `Report`, `Agent`, `Sales`, `Asset`, `Support`); empty means everyone.
* `icon` may be a font class (`fa fa-cube`, `ion ion-cube`), an inline `<svg ...>` or an `<img ...>`; the sidebar gives all of
  them the same size and spacing as the built-in items. Top-level entries with no icon get a generic puzzle icon so the
  labels line up. Prefer a 24x24 viewBox SVG with `stroke="currentColor"` so it follows the theme colours.
* A menu's `function` is the PHP function that renders it. `"myplugin/settings"` calls `myplugin()` and the part after the
  slash arrives in `$routes[2]` (`?_route=plugin/myplugin/settings`).
* **Routes**: `?_route=plugin/<function>` only runs a function the plugin has *declared*. A menu function is declared by
  its menu. Anything else (a webhook, an AJAX endpoint, your own API) goes in the `"routes"` list of `plugin.json` with
  an `access` level: `public` (anyone: verify signatures yourself), `admin` or `customer` (the core checks the sign-in
  first). Single-file plugins call `register_route('fn', 'public')`. See `PLUGINS.md`; check your plugin with
  `php skeletons/build.php check plugin <folder>`.
* **Hooks**: `register_hook('cronjob_end', 'myplugin_cron')`. Find hook names by searching `run_hook(` in
  `system/controllers/` and `system/cron.php`.
* **Settings** live in `tbl_appconfig` (`myplugin_*` keys) and are available in `$config` and, in templates, `$_c`.
* **Database**: create tables in `install.php` (no migrations exist). Use Idiorm (`ORM::for_table(...)`); it binds values.
* **Assets**: `pluginAssetUrl('myplugin')` returns the public URL of `assets/`.
* **Errors** while the file loads are caught and shown on the plugin card as "Error" instead of breaking the site.

## Lifecycle

| Event | What runs |
|---|---|
| Install / reinstall | files copied (old copy backed up), `install.php` once |
| Activate | requirement + licence checks, `install.php` |
| Deactivate | nothing is deleted; the plugin simply stops loading |
| Update | files merged over the existing folder (your data is kept), old version backed up |
| Uninstall | `uninstall.php`, then the folder is removed (backed up first, restorable from Marketplace > Tools) |

## Checklist before release

* Role check and CSRF on every page that changes data (both are in the examples).
* Every webhook / callback / AJAX function is declared in `"routes"` (`php skeletons/build.php check plugin <folder>`).
* Escape output in templates with `|escape`.
* Bump `version` in `plugin.json`; set `github` and/or `update_url` (how updates are served: `PLUGINS.md`, "Publishing and serving updates").
* `php -l myplugin.php includes/*.php install.php uninstall.php`.
