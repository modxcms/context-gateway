<?php
$eventName = $modx->event->name;
if ($eventName !== 'OnSiteRefresh' && $eventName !== 'OnHandleRequest') return '';
$gateway = $modx->getService('gateway', 'Gateway', $modx->getOption('core_path') . 'components/gateway/model/gateway/');
if (!($gateway instanceof Gateway)){
    $modx->log(modX::LOG_LEVEL_ERROR, "Unable to load Gateway class.");
    return '';
}

switch ($eventName) {
    case 'OnSiteRefresh':
        $gateway->init($scriptProperties);
        $gateway->refreshContextCache($partitions);
        break;
    case 'OnHandleRequest':
        //$modx->log(modX::LOG_LEVEL_ERROR, "OnHandleRequest fired on {$_SERVER['REQUEST_URI']}");
        $gateway->init($scriptProperties);
        $gateway->handleRequest();
        break;
    default:
        break;
}