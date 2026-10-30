<?php

// Symcon best practice and language rules (as in IPSTuya)

const MODULES = ['DoorstationAccess' => 'DSA', 'DoorstationBell' => 'DSB'];

// PHP files of one module, or of all modules and the shared libs
function moduleFiles(?string $module = null): array
{
    $files = $module === null ? glob(__DIR__ . '/../libs/*.php') : [];
    foreach ($module === null ? array_keys(MODULES) : [$module] as $m) {
        $files = array_merge($files, glob(__DIR__ . "/../$m/*.php"), glob(__DIR__ . "/../$m/libs/*.php"));
    }
    return $files;
}

$tests['Convention: no IPS_LogMessage, use $this->LogMessage'] = function () {
    foreach (moduleFiles() as $file) {
        check(!str_contains(file_get_contents($file), 'IPS_LogMessage'), basename($file));
    }
};

$tests['Convention: modules do not configure themselves'] = function () {
    foreach (array_keys(MODULES) as $module) {
        $src = file_get_contents(__DIR__ . "/../$module/module.php");
        foreach (['IPS_SetProperty', 'IPS_SetConfiguration', 'IPS_ApplyChanges'] as $fn) {
            check(!str_contains($src, $fn), "$module uses $fn");
        }
    }
};

$tests['Convention: only the needed public functions'] = function () {
    $names = ['Create', 'ApplyChanges', 'MessageSink', 'RequestAction', 'GetConfigurationForm', 'GetVisualizationTile'];
    sort($names);
    foreach (array_keys(MODULES) as $module) {
        $public = [];
        foreach ((new ReflectionClass($module))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class === $module) {
                $public[] = $method->name;
            }
        }
        sort($public);
        check($public === $names, "$module: " . implode(', ', $public));
    }
};

// all translatable texts of a form (caption, label)
function formStrings($node): array
{
    $out = [];
    if (is_array($node)) {
        foreach ($node as $key => $value) {
            if (in_array($key, ['caption', 'label'], true) && is_string($value) && $value !== '') {
                $out[] = $value;
            }
            $out = array_merge($out, formStrings($value));
        }
    }
    return $out;
}

function codeStrings(string $module): array
{
    preg_match_all('/->Translate\("((?:[^"\\\\]|\\\\.)*)"\)/', implode("\n", array_map('file_get_contents', moduleFiles($module))), $m);
    return $m[1];
}

function tileStrings(string $module): array
{
    $file = __DIR__ . "/../$module/module.html";
    preg_match_all("/translate\\('((?:[^'\\\\]|\\\\.)*)'\\)/", is_file($file) ? file_get_contents($file) : '', $m);
    return $m[1];
}

function localeDe(string $module): array
{
    return json_decode(file_get_contents(__DIR__ . "/../$module/locale.json"), true)['translations']['de'] ?? [];
}

// all texts of form, code (module and shared libs) and tile
function moduleStrings(string $module): array
{
    $form = json_decode(file_get_contents(__DIR__ . "/../$module/form.json"), true);
    check(is_array($form), "$module: form.json invalid");
    preg_match_all('/->Translate\("((?:[^"\\\\]|\\\\.)*)"\)/', implode("\n", array_map('file_get_contents', glob(__DIR__ . '/../libs/*.php'))), $shared);
    return array_unique(array_merge(formStrings($form), codeStrings($module), tileStrings($module), $shared[1]));
}

$tests['Language: all texts from form and code are translated'] = function () {
    foreach (array_keys(MODULES) as $module) {
        $missing = array_diff(moduleStrings($module), array_keys(localeDe($module)));
        check($missing === [], "$module: " . implode(' | ', $missing));
    }
};

$tests['Language: no unused translations'] = function () {
    foreach (array_keys(MODULES) as $module) {
        $unused = array_diff(array_keys(localeDe($module)), moduleStrings($module));
        check($unused === [], "$module: " . implode(' | ', $unused));
    }
};

$tests['Language: library.json and module.json are valid'] = function () {
    $lib = json_decode(file_get_contents(__DIR__ . '/../library.json'), true);
    check(($lib['compatibility']['version'] ?? '') === '9.0', 'library compatibility');
    $ids = [];
    foreach (MODULES as $module => $prefix) {
        $mod = json_decode(file_get_contents(__DIR__ . "/../$module/module.json"), true);
        check(($mod['prefix'] ?? '') === $prefix && ($mod['type'] ?? 0) === 3 && ($mod['name'] ?? '') === $module, "$module: module.json");
        $ids[] = $mod['id'];
    }
    check(count(array_unique($ids)) === count($ids), 'module IDs not unique');
};
