<?php
/* 
 * getContextSettings Snippet
 * Version 0.0.1 
 * Author - YJ Tso <yj@modx.com> based on work by John Peca <john@modx.com>
 * 
 * 
*/ 
$contexts = array();

$cacheKey = $modx->getOption('cache_system_settings_key', null, 'system_settings');
$cacheOptions = array(
    xPDO::OPT_CACHE_HANDLER => $modx->getOption("cache_{$cacheKey}_handler", $scriptProperties, $modx->getOption(xPDO::OPT_CACHE_HANDLER)),
    xPDO::OPT_CACHE_EXPIRES => $modx->getOption("cache_{$cacheKey}_expires", $scriptProperties, $modx->getOption(xPDO::OPT_CACHE_EXPIRES)),
);
/** @var xPDOCache $contextCache */
$contextCache = $modx->cacheManager->getCacheProvider($cacheKey, $cacheOptions);

if ($contextCache) {
    $contexts = $contextCache->get('context_map');
}

if (empty($contexts)) {
    /** @var modContext $contextsGraph */
    $query = $modx->newQuery('modContext');
    $query->where(array('modContext.key:NOT IN' => array('web', 'mgr')));
    $query->sortby($modx->escape('modContext') . '.' . $modx->escape('key'), 'ASC');
    $contextsGraph = $modx->getCollectionGraph('modContext', '{"ContextSettings":{}}', $query);
    foreach ($contextsGraph as $context) {
        $contextSettings = array();
        foreach ($context->ContextSettings as $cSetting) {
            $contextSettings[$cSetting->get('key')] = $cSetting->get('value');
        }
        $contexts[$context->get('key')] = $contextSettings;
    }
    unset($contextsGraph);
    if ($contextCache) {
        $contextCache->set('context_map', $contexts);
    }
}

// Options for settings
$settingTpl = $modx->getOption('settingTpl', $scriptProperties, '');
$settingSeparator = $modx->getOption('settingSeparator', $scriptProperties, PHP_EOL);
$settingLimit = $modx->getOption('settingLimit', $scriptProperties, '0');
$namespace = $modx->getOption('namespace', $scriptProperties, '');

// Options for contexts
$contextTpl = $modx->getOption('contextTpl', $scriptProperties, '');
$contextSeparator = $modx->getOption('contextSeparator', $scriptProperties, PHP_EOL);
$contextLimit = $modx->getOption('contextLimit', $scriptProperties, '0');
$exclude = $modx->getOption('exclude', $scriptProperties, ''); // coma separated list of excluded contexts

// Option for debugging
$debug = $modx->getOption('debug', $scriptProperties, false);

// prepare excluded contexts into array
$exclude = explode(',', $exclude);
foreach ($exclude AS $key => $value) {
    $exclude[$key] = trim($value);
}

$ctxOut = array();
$ctxIdx = 0;
foreach ($contexts as $key => $context) {
    if (in_array($key, $exclude)) continue;
    
    $stgOut = array();
    $stgIdx = 0;
    foreach ($context as $setting => $value) {
        if (!empty($namespace) && (strpos($setting, $namespace) !== 0)) continue;
        if (empty($settingTpl)) {
            if ($debug) $stgOut[] = print_r($context[$setting], true);
            continue;
        }
        $stgOut[] = $modx->getChunk($settingTpl, array('key' => $setting, 'value' => $value, 'idx' => $idx));
        $stgIdx++;
    }
    $context['settings'] = ($debug) ? $stgOut : implode($settingSeparator, $stgOut);
    $context['context_key'] = $key;
    $context['idx'] = $idx;
    
    if (empty($contextTpl)) {
        if ($debug) $ctxOut[] = print_r($context, true);
        continue;
    }
    $ctxOut[] = $modx->getChunk($contextTpl, $context);
}

if ($debug) return print_r($ctxOut, true);
return implode($contextSeparator, $ctxOut);
