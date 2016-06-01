<?php
/* 
 * getContextSettings Snippet
 * Version 0.0.1 
 * Author - YJ Tso <yj@modx.com> based on work by John Peca <john@modx.com>
 * @package Gateway
 * 
 * 
*/ 
$corePath = $modx->getOption('gateway.core_path', null, MODX_CORE_PATH . 'components/gateway/');
$modelPath = $corePath . 'model/gateway/';
$gateway = $modx->getService('gateway', 'Gateway', $modelPath);
if (!($gateway instanceof Gateway)){
    $modx->log(modX::LOG_LEVEL_ERROR, "getContextSettings snippet could not load Gateway class.");
    return '';
}
$gateway->init($scriptProperties);
$contexts = $gateway->getContexts();

if (!$contexts) {
    $modx->log(modX::LOG_LEVEL_ERROR, "Unable to fetch contexts map.");
    return '';
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
// coma separated list of excluded contexts, overrides include
$exclude = array_filter(array_map('trim', explode(',', $modx->getOption('exclude', $scriptProperties, '')))); 
// coma separated list of included contexts
$include = array_filter(array_map('trim', explode(',', $modx->getOption('include', $scriptProperties, '')))); 

// Option for debugging
$debug = $modx->getOption('debug', $scriptProperties, false);

$ctxOut = array();
$ctxIdx = 0;
foreach ($contexts as $key => $context) {
    // If excluded context, skip it
    if (!empty($exclude) && in_array($key, $exclude)) continue;
    // If included contexts are specified and this isn't one, skip it
    if (!empty($include) && !in_array($key, $include)) continue;
    
    // Respect limit param (we're using 1-based indexing in the output, btw)
    $ctxIdx++;
    if (($contextLimit) && ($ctxIdx > $contextLimit)) break;
    
    // Get settings
    $stgOut = array();
    $stgIdx = 0;
    foreach ($context as $setting => $value) {
        // If namespace is set, only grab those settings
        if (!empty($namespace) && (strpos($setting, $namespace) !== 0)) continue;
        // Know your limits
        $stgIdx++;
        if (($settingLimit) && ($stgIdx > $settingLimit)) break;
        // If we're debugging then do that otherwise there's nothing left to do
        if ($debug) {
            $stgOut[] = print_r(array($setting => $value), true);
            // Continue to debug or do nothing
            continue;
        }
        // Format with settingTpl 
        $stgOut[] = $modx->getChunk($settingTpl, array('key' => $setting, 'value' => $value, 'idx' => $idx));
    }

    // Output settings to placeholder in wrapper chunk
    $context['settings'] = ($debug) ? $stgOut : implode($settingSeparator, $stgOut);

    // Set some useful placeholders
    $context['context_key'] = $key;
    $context['idx'] = $ctxIdx;
    
    // If we're debugging...
    if ($debug) {
        $ctxOut[] = print_r($context, true);
        // Continue to debug or do nothing
        continue;
    }

    // Note the $context has every setting, 
    // AS WELL AS a placeholder 'settings' that holds all templated settings
    $ctxOut[] = $modx->getChunk($contextTpl, $context);
}

// Return
if ($debug) return '<pre>' . print_r($ctxOut, true) . '</pre>';
return implode($contextSeparator, $ctxOut);