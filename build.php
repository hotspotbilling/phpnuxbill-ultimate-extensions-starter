<?php

/**
 *  PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *  by https://t.me/ibnux
 *
 *  Skeleton builder (CLI only).
 *
 *    php skeletons/build.php list
 *    php skeletons/build.php zip                          zip every skeleton as-is into skeletons/dist/
 *    php skeletons/build.php new <type> <slug> "<Name>"   copy a skeleton under a new name and zip it
 *    php skeletons/build.php check plugin <folder|file>   which functions a plugin's URLs call but it has not declared
 *
 *  <type> is plugin | gateway | theme | device.
 *  Slug rules: plugin/theme  letters, digits, _ and -   (e.g. acme_sms)
 *              gateway       letters, digits, _         (functions are named <slug>_*; start with a letter)
 *              device        a PHP class name           (e.g. AcmeRouter)
 *
 *  Output: skeletons/dist/<slug>/ (the folder to drop into the matching system folder) and
 *  skeletons/dist/<slug>.zip (upload it in Marketplace, or unzip it there yourself).
 */

if (php_sapi_name() !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit('CLI only');
}

const SKELETONS = [
    'plugin' => ['dir' => 'plugin', 'placeholder' => 'myplugin', 'label' => 'My Plugin', 'target' => 'system/plugin/<slug>/'],
    'gateway' => ['dir' => 'gateway', 'placeholder' => 'mygateway', 'label' => 'My Gateway', 'target' => 'system/paymentgateway/<slug>/'],
    'theme' => ['dir' => 'theme', 'placeholder' => 'mytheme', 'label' => 'My Theme', 'target' => 'ui/themes/<slug>/'],
    'device' => ['dir' => 'device', 'placeholder' => 'MyDevice', 'label' => 'My Device', 'target' => 'system/devices/<slug>/'],
];

function out(string $m): void
{
    fwrite(STDOUT, $m . "\n");
}

function fail(string $m): void
{
    fwrite(STDERR, "Error: $m\n");
    exit(1);
}

function copyTree(string $from, string $to, callable $rename): int
{
    $n = 0;
    mkdir($to, 0755, true);
    foreach (scandir($from) as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        $src = $from . '/' . $f;
        $dst = $to . '/' . $rename($f);
        if (is_dir($src)) {
            $n += copyTree($src, $dst, $rename);
        } else {
            $n += copy($src, $dst) ? 1 : 0;
        }
    }
    return $n;
}

function rewrite(string $dir, array $pairs): void
{
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || !preg_match('/\.(php|json|tpl|css|md|js)$/i', $f->getFilename())) {
            continue;
        }
        $c = file_get_contents($f->getPathname());
        file_put_contents($f->getPathname(), str_replace(array_keys($pairs), array_values($pairs), $c));
    }
}

function zipDir(string $dir, string $zipFile): void
{
    if (!class_exists('ZipArchive')) {
        fail('PHP ZIP extension is not available');
    }
    @unlink($zipFile);
    $z = new ZipArchive();
    if ($z->open($zipFile, ZipArchive::CREATE) !== true) {
        fail("Cannot create $zipFile");
    }
    $base = realpath($dir);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile()) {
            $z->addFile($f->getPathname(), substr($f->getPathname(), strlen($base) + 1));
        }
    }
    $z->close();
}

/**
 * Read a plugin and report the functions its own addresses call (plugin/<function> in PHP, templates, scripts) that are
 * defined but not declared as a menu or a route. See PLUGINS.md.
 * @return int exit code (0 = nothing to declare)
 */
function checkPlugin(string $path): int
{
    $real = realpath($path) ?: realpath(__DIR__ . '/../' . $path);
    if ($real === false) {
        fail("not found: $path");
    }
    $files = [];
    if (is_dir($real)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && preg_match('/\.(php|tpl|html|js|json)$/i', $f->getFilename())
                && !preg_match('#/(vendor|node_modules|\.git)/#', $f->getPathname())) {
                $files[] = $f->getPathname();
            }
        }
    } else {
        $files[] = $real;
    }

    $defined = [];
    $referenced = [];
    $declared = [];
    $routesInManifest = [];
    foreach ($files as $file) {
        $c = (string) file_get_contents($file);
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'php') {
            if (preg_match_all('/^\s*function\s+([A-Za-z_]\w*)\s*\(/m', $c, $m)) {
                foreach ($m[1] as $fn) {
                    $defined[$fn] = true;
                }
            }
            if (preg_match_all('/register_route\(\s*[\'"]([A-Za-z_]\w*)[\'"]/', $c, $m)) {
                foreach ($m[1] as $fn) {
                    $declared[$fn] = 'register_route';
                }
            }
            if (preg_match_all('/register_menu\(\s*[^,]+,\s*(?:true|false)\s*,\s*[\'"]([\w\/]+)[\'"]/i', $c, $m)) {
                foreach ($m[1] as $fn) {
                    $declared[strtok($fn, '/')] = 'menu';
                }
            }
            if (preg_match_all('/register_submenu\(\s*[^,]+,\s*[^,]+,\s*(?:true|false)\s*,\s*[\'"]([\w\/]+)[\'"]/i', $c, $m)) {
                foreach ($m[1] as $fn) {
                    $declared[strtok($fn, '/')] = 'menu';
                }
            }
        }
        if (basename($file) === 'plugin.json') {
            $j = json_decode($c, true);
            foreach ((array) ($j['routes'] ?? []) as $r) {
                $fn = is_array($r) ? ($r['function'] ?? '') : (string) $r;
                if ($fn !== '') {
                    $declared[$fn] = 'plugin.json routes';
                    $routesInManifest[$fn] = true;
                }
            }
            foreach ((array) ($j['menus'] ?? []) as $mi) {
                if (!empty($mi['function'])) {
                    $declared[strtok((string) $mi['function'], '/')] = 'menu';
                }
            }
        } elseif (preg_match_all('/(?:route=|["\'\/(}])plugin\/([A-Za-z_]\w*)/', $c, $m)) {
            foreach ($m[1] as $fn) {
                $referenced[$fn] = basename($file);
            }
        }
    }

    out('Plugin: ' . $real);
    out(sprintf('Functions defined: %d, declared (menus + routes): %d, addresses found in the code: %d', count($defined), count($declared), count($referenced)));

    $missing = [];
    $stale = [];
    foreach ($referenced as $fn => $where) {
        if (isset($declared[$fn])) {
            continue;
        }
        if (isset($defined[$fn])) {
            $missing[$fn] = $where;
        } elseif ($fn !== 'hotspot' && !in_array($fn, ['ui', 'index'], true)) {
            $stale[$fn] = $where;
        }
    }
    // entry points that look like webhooks but no address names them (a provider has the URL, not the code)
    $looksPublic = [];
    foreach (array_keys($defined) as $fn) {
        if (!isset($declared[$fn]) && !isset($missing[$fn]) && preg_match('/(webhook|webhook_verify|webhook_notification|callback|_ipn|_api|ajax)$/i', $fn)) {
            $looksPublic[] = $fn;
        }
    }

    if ($missing) {
        out("\nCalled by an address but not declared:");
        foreach ($missing as $fn => $where) {
            out("  $fn   (found in $where)");
        }
    }
    if ($looksPublic) {
        out("\nLooks like an entry point a provider or app calls, not declared:");
        foreach ($looksPublic as $fn) {
            out("  $fn");
        }
    }
    if ($stale) {
        out("\nAn address names a function that is not defined here (stale link or another plugin):");
        foreach ($stale as $fn => $where) {
            out("  $fn   (in $where)");
        }
    }
    $todo = array_merge(array_keys($missing), $looksPublic);
    $undefinedDeclared = [];
    foreach ($routesInManifest as $fn => $_) {
        if (!isset($defined[$fn])) {
            $undefinedDeclared[] = $fn;
        }
    }
    if ($undefinedDeclared) {
        out("\nDeclared in plugin.json but not defined in any PHP file here: " . implode(', ', $undefinedDeclared));
    }
    if (!$todo) {
        out("\nNothing to declare.");
        return 0;
    }
    out("\nAdd to plugin.json (choose 'public' for webhooks, callbacks and APIs, 'admin' or 'customer' for pages):");
    $entries = array_map(fn($fn) => '    { "function": "' . $fn . '", "access": "admin" }', $todo);
    out('  "routes": [' . "\n" . implode(",\n", $entries) . "\n  ]");
    out("or, in a single-file plugin:");
    foreach ($todo as $fn) {
        out("  register_route('$fn', 'admin');");
    }
    return 1;
}

$cmd = $argv[1] ?? 'list';
$root = __DIR__;
$dist = $root . '/dist';

switch ($cmd) {
    case 'list':
        foreach (SKELETONS as $type => $s) {
            out(str_pad($type, 8) . ' skeletons/' . $s['dir'] . '/  ->  installs to ' . $s['target']);
        }
        break;

    case 'zip':
        @mkdir($dist, 0755, true);
        foreach (SKELETONS as $type => $s) {
            $zip = $dist . '/' . $type . '-skeleton.zip';
            zipDir($root . '/' . $s['dir'], $zip);
            out("Built skeletons/dist/{$type}-skeleton.zip");
        }
        break;

    case 'new':
        $type = $argv[2] ?? '';
        $slug = $argv[3] ?? '';
        $name = $argv[4] ?? '';
        if (!isset(SKELETONS[$type])) {
            fail('type must be one of: ' . implode(', ', array_keys(SKELETONS)));
        }
        if ($name === '') {
            fail('usage: php skeletons/build.php new <type> <slug> "<Display Name>"');
        }
        $rule = [
            'plugin' => '/^[A-Za-z][A-Za-z0-9_-]*$/',
            'theme' => '/^[A-Za-z][A-Za-z0-9_-]*$/',
            'gateway' => '/^[A-Za-z][A-Za-z0-9_]*$/',
            'device' => '/^[A-Za-z_][A-Za-z0-9_]*$/',
        ][$type];
        if (!preg_match($rule, $slug)) {
            fail("invalid slug '$slug' for a $type (see the header of this file)");
        }
        if (in_array($type, ['plugin', 'gateway', 'theme'], true) && $slug !== strtolower($slug)) {
            fail('use a lowercase slug');
        }
        $s = SKELETONS[$type];
        $ph = $s['placeholder'];
        $target = $dist . '/' . $slug;
        if (is_dir($target)) {
            fail("skeletons/dist/$slug already exists; remove it first");
        }
        copyTree($root . '/' . $s['dir'], $target, fn($f) => str_replace($ph, $slug, $f));
        $pairs = [$ph => $slug, $s['label'] => $name];
        if ($type === 'gateway') {
            $pairs['MYGATEWAY'] = strtoupper($slug);
        }
        if ($type === 'plugin') {
            $pairs['My plugin'] = $name;
        }
        if ($type === 'device') {
            // the class is CamelCase (MyDevice) but its helper functions and settings keys are lowercase (mydevice_...)
            $pairs['mydevice'] = strtolower($slug);
        }
        rewrite($target, $pairs);
        zipDir($target, $dist . '/' . $slug . '.zip');
        out("Created skeletons/dist/$slug/  and  skeletons/dist/$slug.zip");
        out('Install: Marketplace > upload the zip, or copy the folder to ' . str_replace('<slug>', $slug, $s['target']));
        break;

    case 'check':
        if (($argv[2] ?? '') !== 'plugin' || empty($argv[3])) {
            fail('usage: php skeletons/build.php check plugin <folder|file.php>');
        }
        exit(checkPlugin($argv[3]));

    default:
        fail("unknown command '$cmd' (list | zip | new | check)");
}
