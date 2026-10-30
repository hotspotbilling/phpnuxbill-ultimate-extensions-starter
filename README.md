# Extension skeletons

Starter packages for every kind of PHPNuxBill extension. Each one is a **folder-style** package, so the Marketplace can
install, update, back up, restore and uninstall it, and it declares what it needs (PHP version, extensions, licence...).

| Skeleton | Folder | Manifest | Installs to | Entry point |
|---|---|---|---|---|
| [Plugin](plugin/README.md) | `skeletons/plugin/` | `plugin.json` | `system/plugin/<slug>/` | `<slug>.php` (hooks, menus, pages) |
| [Payment gateway](gateway/README.md) | `skeletons/gateway/` | `gateway.json` | `system/paymentgateway/<slug>/` | `<slug>.php` (`<slug>_*` functions) |
| [Theme](theme/README.md) | `skeletons/theme/` | `theme.json` | `ui/themes/<slug>/` | templates + `functions.php` |
| [Device driver](device/README.md) | `skeletons/device/` | `device.json` | `system/devices/<Class>/` | `<Class>.php` (one class) |

Gateways and devices use exactly the same folder method as plugins and themes: a manifest named after the type, a main PHP
file named after the slug, and optional `ui/` for templates. The older single-file layout still works for gateways, devices
and plugins; Marketplace has a "Convert to folder" button for it.

## Quick start

```bash
# list the skeletons
php skeletons/build.php list

# make your own copy: renames files, class/function prefixes, titles and the table name, then zips it
php skeletons/build.php new plugin  acme_sms    "Acme SMS"
php skeletons/build.php new gateway acmepay     "Acme Pay"
php skeletons/build.php new theme   neon_blue   "Neon Blue"
php skeletons/build.php new device  AcmeRouter  "Acme Router"

# or zip the untouched skeletons
php skeletons/build.php zip
```

Output goes to `skeletons/dist/<slug>/` (the folder) and `skeletons/dist/<slug>.zip`.

Install it either way:

* **Marketplace**: open the matching tab (Plugins, Payment Gateways, Themes, Device Drivers), press **Upload**, pick the zip.
  The package is syntax-checked, its requirements are verified, and any existing copy is backed up first.
* **By hand**: copy `skeletons/dist/<slug>/` into the folder shown in the table.

Slug rules (the build script enforces them):

| Type | Slug | Why |
|---|---|---|
| plugin, theme | lowercase letters, digits, `_` `-` | used as folder and URL name |
| gateway | lowercase letters, digits, `_`, starting with a letter | every function is named `<slug>_...` |
| device | a PHP class name, e.g. `AcmeRouter` | the engine calls `new <Class>` |

## What a manifest can say

All four manifests share these keys (all optional except `name` and `slug`):

```json
{
  "name": "Acme SMS",
  "slug": "acme_sms",
  "version": "1.0.0",
  "description": "What it does",
  "author": "You",
  "requires_php": "8.2",
  "requires_extensions": ["curl"],
  "requires_version": "2025.11.1",
  "requires_plugins": ["hotspot"],
  "requires_license": false,
  "license_check_url": "",
  "update_url": "https://api.github.com/repos/you/repo/releases/latest",
  "github": "https://github.com/you/repo"
}
```

* `requires_*` are checked before install and shown on the card. A missing PHP version/extension blocks the install.
* `update_url` returns JSON with `version` (or a GitHub release `tag_name`); the Marketplace compares it with `version`.
* `github` lets the Marketplace update the extension from its repository.
* Licensing: put your own rules in code with `register_license($slug, $statusFn, $saveFn)` (see `PLUGINS.md` > Licensing); the manifest flag below is editable by the customer and is only a reminder.
* `requires_license: true` blocks activation until the admin saves a key. There is no separate License page: once the extension is installed, its card in Marketplace (Plugins, Payment Gateways, Devices or Themes) shows a *License needed* badge and a **License** button, which opens the key box. The extension is **not run** until then (a plugin is not loaded, a gateway or driver is not used, a theme falls back to the default). If `license_check_url` is
  set, the key is POSTed there (`key`, `slug`, `type`, `domain`) and must answer `{"valid": true}`. Add `license_public_key` (base64 Ed25519) and the answer must also carry `expires` and a `signature` over `type|slug|host|key|expires|valid`, checked locally on every request (see `PLUGINS.md` > Licensing). Read the key from your
  code with `Extensions::license('<type>', '<slug>')`.
* Add `"preview": "preview.png"` to `theme.json` for the theme card image.

## Publishing to a store

A store is a JSON file served over https (add it in Marketplace > Tools > Settings, or send it to the official repository):

```json
{
  "plugins":         [{"id": "acme_sms", "name": "Acme SMS", "description": "...", "ispaid": false, "author": "you",
                       "url": "https://example.com", "github": "https://github.com/you/acme_sms", "last_update": "2026-10-25"}],
  "payment_gateway": [],
  "devices":         [],
  "themes":          []
}
```

The Marketplace installs from `github` (master or main branch). Keep the manifest at the repository root, or inside a
`plugin/`, `paymentgateway/`, `device/` or `theme/` folder. Bump `last_update` when you release: installed copies show an
**Update** button when the store date is newer than the one they were installed from.

## Testing checklist

1. `php -l` on every PHP file (the Marketplace refuses packages with syntax errors).
2. Install the zip in Marketplace, confirm the card shows no error or warning chip.
3. Uninstall it, then **Marketplace > Tools > Backups > Restore** to prove the backup works.
4. Keep `$_app_stage` on `Demo`/`Dev` while testing payments and driver calls.

Security reminders that apply to every extension: check `_admin()` / `_auth()` and the role first; verify
`Csrf::check(_post('csrf_token'))` on every state-changing request; escape output (`|escape`) and never concatenate user input
into SQL (Idiorm binds values); verify webhook signatures before trusting a body.

`skeletons/` has an `.htaccess` that blocks web access; `skeletons/dist/` is git-ignored.
