<?php

/**
 *  PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *  by https://t.me/ibnux
 *
 *  My Theme - loaded automatically on every web request while the theme is active
 *  (child theme first, then its parents), after Smarty ($ui) and $_theme exist.
 **/

global $ui, $_theme, $config;

// ---------------------------------------------------------------------------------------------------------------------
// WHAT IS AVAILABLE HERE
//   $ui       the Smarty instance every page renders with (assign values, register filters, modifiers and functions)
//   $_theme   the public URL of this theme folder (also {$_theme} in templates: images, CSS, JS)
//   $config   settings from tbl_appconfig
// This file runs once per web request while the theme is active, before any template is drawn. It must not print or exit, and
// a PHP error in it prevents the theme from being activated.
// ---------------------------------------------------------------------------------------------------------------------

/**
 * 1. Inject the theme stylesheet into every full HTML page, whichever controller rendered it.
 * (A Smarty output filter only touches template output, so JSON/API responses and downloads are untouched.)
 * Templates are resolved child theme -> parent theme -> default, so you only copy the templates you change.
 *
 * The customer portal's chrome is re-skinned separately and automatically by styles/customer.css (colour tokens only).
 */
$mythemeCss = '<link rel="stylesheet" href="' . htmlspecialchars($_theme, ENT_QUOTES) . '/styles/theme.css?v=1.0.0">';
$ui->registerFilter('output', function ($html, $template) use ($mythemeCss) {
    if (stripos($html, '</head>') === false) {
        return $html;
    }
    return preg_replace('~</head>~i', $mythemeCss . "\n</head>", $html, 1);
});

/**
 * 2. Give templates a value: {$mytheme_tagline} in any template of this theme.
 * Prefix everything (mytheme_) so two themes or plugins never collide.
 */
$ui->assign('mytheme_tagline', $config['mytheme_tagline'] ?? '');

/**
 * 3. Add a template helper: {$price|mytheme_money} prints "$12.50". Smarty 4 needs modifiers to be registered, which is also how
 * you expose any PHP function to templates. Escape what you print: Smarty does not escape by default (use |escape).
 */
$ui->registerPlugin('modifier', 'mytheme_money', function ($amount) {
    return Lang::moneyFormat((float) $amount);
});
