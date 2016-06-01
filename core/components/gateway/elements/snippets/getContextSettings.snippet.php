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

$tplRow = $modx->getOption('tplRow', $scriptProperties, '');
$tplOuter = $modx->getOption('tplOuter', $scriptProperties, '');
$separator = $modx->getOption('separator', $scriptProperties, '');
$onlyUpcoming = $modx->getOption('onlyUpcoming', $scriptProperties, '0');
$includeUpcoming = $modx->getOption('includeUpcoming', $scriptProperties, '1');
$getLatLong = $modx->getOption('getLatLong', $scriptProperties, '0');
$limit = $modx->getOption('limit', $scriptProperties, '0');
$offset = $modx->getOption('offset', $scriptProperties, '0');
$exclude = $modx->getOption('exclude', $scriptProperties, ''); // coma separated list of excluded contexts

$outLoadMore ='';
$out = [];

// prepare excluded contexts into array
$exclude = explode(',', $exclude);
foreach ($exclude AS $key => $value) {
    $exclude[$key] = trim($value);
    
}

if($getLatLong == 1) {
    if (isset($_POST['location-geocode'])){ 
        $geocode = explode(';', $_POST['location-geocode']);
        $latLong = array(3 => $geocode[0], 4 => $geocode[1]);
    
    // @dubrod - we are removing the session since no devs knew where to set it
    //} elseif (isset($_SESSION['location-geocode'])){ 
    //$geocode = explode(';', $_SESSION['location-geocode']);
    
    //this cookie is set via the HTML5 Mobile Splash Page
    } elseif (isset($_COOKIE["userGPS"])){ 
        $geocode = preg_split("/[\s|]+/", $_COOKIE["userGPS"]);
        $latLong = array(3 => $geocode[0], 4 => $geocode[1]);
    } else {  
        // else we use the fall back maxmind api
        $latLong = $modx->fromJSON($modx->runSnippet('getLatLong'));
    }

    foreach ($contexts as $key => $context) {
        $contexts[$key]['distance'] = (int)$modx->runSnippet('getDistance', array('userLat' => $latLong[3], 'userLong' => $latLong[4], 'cityLat' => $context['lat'], 'cityLong' => $context['long']));   
    }
    
    uasort($contexts, function($a, $b) {
        return $a['distance'] - $b['distance'];
    });
} else {
    // Sort contexts array by state and context_title
    $title = array();
    $state = array();
    foreach ($contexts as $key => $row) {
        $title[$key]  = $row['context_title'];
        $state[$key] = $row['state'];
    }
    
    array_multisort($state, SORT_ASC, SORT_STRING, $title, SORT_ASC, SORT_STRING, $contexts);
}

if ($parent == '') {
    $idx = 0;
    foreach ($contexts as $key => $context) {
        if (!isset($context['ctx_parent'])) continue;
        if ($context['disabled'] == 1) continue;
        if ($context['upcoming'] != 1 && $onlyUpcoming == 1) continue;
        if ($context['upcoming'] == 1 && $onlyUpcoming == 0 && $includeUpcoming == 0) continue;
        if (in_array($key, $exclude)) continue;
        
        $idx++;
        $context['idx'] = $idx;
        $context['context_key'] = $key;
        if($idx>$offset){
            $out[] = $modx->getChunk($tplRow, $context);
        }
        if ($idx == ($limit + $offset) && $limit !=0 ){
            $context['limit'] = $limit;
            $context['parent'] = $parent;
            $context['latLong'] = $latLong;
            $outLoadMore = $modx->getChunk($tplLoadMore, $context);
            break;    
        }
    }
} else {
    $idx = 0;
    foreach ($contexts as $key => $context) {
        if ($key == $parent) continue;
        if (!isset($context['ctx_parent'])) continue;
        if ($context['disabled'] == 1) continue;
        if ($context['upcoming'] != 1 && $onlyUpcoming == 1) continue;
        if ($context['upcoming'] == 1 && $onlyUpcoming == 0 && $includeUpcoming == 0) continue;
        if (in_array($key, $exclude)) continue;
        if ($context['ctx_parent'] == $parent) {
            $idx++;
            $context['idx'] = $idx;
            $context['context_key'] = $key;
            if($idx>$offset){
               $out[] = $modx->getChunk($tplRow, $context);
            }
            if ($idx == ($limit + $offset) && $limit !=0){
                $context['limit'] = $limit;
                $context['latLong'] = $latLong;
                $context['parent'] = $parent;
                $outLoadMore = $modx->getChunk($tplLoadMore, $context);
                break;    
            }
            
        }
    }
}

$debug = $modx->getOption('debug', $scriptProperties, false);
if ($debug) print_r($contexts);
return $modx->getChunk($tplOuter, array('locations' => implode($separator, $out),'loadMore' => $outLoadMore));

