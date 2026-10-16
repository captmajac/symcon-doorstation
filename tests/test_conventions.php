<?php

// Symcon best practice and language rules (as in IPSTuya)

function moduleFiles(): array
{
    return array_merge(glob(__DIR__ . '/../DoorstationAccess/*.php'), glob(__DIR__ . '/../DoorstationAccess/libs/*.php'));
}

$tests['Convention: no IPS_LogMessage, use $this->LogMessage'] = function () {
    foreach (moduleFiles() as $file) {
        check(!str_contains(file_get_contents($file), 'IPS_LogMessage'), basename($file));
    }
};

$tests['Convention: module does not configure itself'] = function () {
    $src = file_get_contents(__DIR__ . '/../DoorstationAccess/module.php');
    foreach (['IPS_SetProperty', 'IPS_SetConfiguration', 'IPS_ApplyChanges'] as $fn) {
        check(!str_contains($src, $fn), "uses $fn");
    }
};

$tests['Convention: only the needed public functions'] = function () {
    $names = ['Create', 'ApplyChanges', 'MessageSink', 'RequestAction', 'GetConfigurationForm', 'GetVisualizationTile'];
    $public = [];
    foreach ((new ReflectionClass(DoorstationAccess::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->class === DoorstationAccess::class) {
            $public[] = $method->name;
        }
    }
    sort($public); sort($names);
    check($public === $names, implode(', ', $public));
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

function codeStrings(): array
{
    preg_match_all('/->Translate\("((?:[^"\\\\]|\\\\.)*)"\)/', implode("\n", array_map('file_get_contents', moduleFiles())), $m);
    return $m[1];
}

function tileStrings(): array
{
    $file = __DIR__ . '/../DoorstationAccess/module.html';
    preg_match_all("/translate\\('((?:[^'\\\\]|\\\\.)*)'\\)/", is_file($file) ? file_get_contents($file) : '', $m);
    return $m[1];
}

function localeDe(): array
{
    return json_decode(file_get_contents(__DIR__ . '/../DoorstationAccess/locale.json'), true)['translations']['de'] ?? [];
}

$tests['Language: all texts from form and code are translated'] = function () {
    $form = json_decode(file_get_contents(__DIR__ . '/../DoorstationAccess/form.json'), true);
    check(is_array($form), 'form.json invalid');
    $missing = array_diff(array_unique(array_merge(formStrings($form), codeStrings(), tileStrings())), array_keys(localeDe()));
    check($missing === [], implode(' | ', $missing));
};

$tests['Language: no unused translations'] = function () {
    $form = json_decode(file_get_contents(__DIR__ . '/../DoorstationAccess/form.json'), true);
    $unused = array_diff(array_keys(localeDe()), array_merge(formStrings($form), codeStrings(), tileStrings()));
    check($unused === [], implode(' | ', $unused));
};

$tests['Language: library.json and module.json are valid'] = function () {
    $lib = json_decode(file_get_contents(__DIR__ . '/../library.json'), true);
    $mod = json_decode(file_get_contents(__DIR__ . '/../DoorstationAccess/module.json'), true);
    check(($lib['compatibility']['version'] ?? '') === '9.0', 'library compatibility');
    check(($mod['prefix'] ?? '') === 'DSA' && ($mod['type'] ?? 0) === 3, 'module.json');
};
