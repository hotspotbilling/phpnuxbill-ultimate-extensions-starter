# Theme skeleton

A **child theme**: it declares `"parent": "default"`, ships its own stylesheet and a preview image, and falls back to the
default templates for everything it does not override.

```
mytheme/
├── theme.json          manifest (name, parent, preview, requirements)
├── functions.php       loaded on every request while active (adds the stylesheet)
├── styles/theme.css    your overrides
├── styles/customer.css re-skins the customer portal chrome through --cm-* colour tokens (picked up automatically)
└── preview.png         640x360 card image shown in Marketplace > Themes (replace it)
```

## What the skeleton demonstrates

| File | APIs |
|---|---|
| `theme.json` | `parent`, `preview`, requirements, `update_url` / `github` |
| `functions.php` | `$ui->registerFilter('output', ...)` to add a stylesheet, `$ui->assign()` to give templates a value, `$ui->registerPlugin('modifier', ...)` to expose a PHP function to templates, `$_theme`, `$config` |
| `styles/theme.css` | the stylesheet for admin and customer pages |
| `styles/customer.css` | the customer chrome tokens (`--cm-*`), light and dark |
| templates (none shipped) | copy a file from `ui/ui/` to the same path in the theme to override it; use `{$_theme}`, `{$mytheme_tagline}`, `{$price|mytheme_money}` |

A theme can also be licensed: add `"requires_license": true` (a theme has no PHP entry point of its own, so the manifest mode is the
only one; the core falls back to the default look until a valid key is saved). See `PLUGINS.md` > Licensing.

## Make it yours

```bash
php skeletons/build.php new theme neon_blue "Neon Blue"
```

then edit `styles/theme.css` (start with the `--mytheme-*` variables) and replace `preview.png` with a screenshot.

## How themes resolve templates

Smarty looks for a template in this order: `ui/ui_custom/` -> your theme -> its parent theme(s) -> `ui/ui/` (default). So
to change a page, copy **only that file** from `ui/ui/` into the same relative path inside your theme and edit it:

```
ui/ui/admin/customers/list.tpl      ->  ui/themes/mytheme/admin/customers/list.tpl
ui/ui/customer/dashboard.tpl        ->  ui/themes/mytheme/customer/dashboard.tpl
ui/ui/sections/header.tpl           ->  ui/themes/mytheme/sections/header.tpl
```

Nothing else needs copying. Inside templates `{$_theme}` is the public URL of your theme folder (use it for images, CSS
and JS: `<img src="{$_theme}/images/logo.png">`).

## functions.php

Runs once per web request (child first, then parents) with `$ui` (Smarty) and `$_theme` available. The skeleton registers a
Smarty output filter that inserts `<link rel="stylesheet" href=".../styles/theme.css">` before `</head>` of every HTML page,
so it works whichever controller renders. Use it for theme helpers, extra Smarty plugins or `$ui->assign(...)`.
It must not output anything itself and must not `exit`. A PHP error in `functions.php` prevents the theme from being
activated.

## Dark mode

The panel marks dark pages with the `dark-mode` class on `<body>` (the default theme also uses `modern-skin-dark`). Scope dark rules with `.dark-mode .your-selector { ... }` and, to cover both, `.dark-mode .x, .modern-skin-dark .x { ... }`.

## Child themes of a theme

`"parent"` may name any installed theme (for example `shadcn`); templates fall back through the chain. If the parent is not
installed the default templates are used.

## Release checklist

* Replace `preview.png`, set `author`, `description`, `version`.
* Add `github` / `update_url` so installs can update.
* Activate it in Marketplace > Themes; to roll back, press **Switch to default**.
