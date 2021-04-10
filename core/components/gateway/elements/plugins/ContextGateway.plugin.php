<?php
$eventName = $modx->event->name;
if ($eventName !== 'OnSiteRefresh' && $eventName !== 'OnHandleRequest') return '';

$corePath = $modx->getOption('gateway.core_path', null, MODX_CORE_PATH . 'components/gateway/');
$modelPath = $corePath . 'model/gateway/';
$gateway = $modx->getService('gateway', 'Gateway', $modelPath);
if (!($gateway instanceof Gateway)){
    $modx->log(modX::LOG_LEVEL_ERROR, "ContextGateway plugin could not load Gateway class.");
    return '';
}

switch ($eventName) {
    case 'OnSiteRefresh':
        $modx->log(modX::LOG_LEVEL_INFO, 'ContextGateway plugin is refreshing the context map.');
        $gateway->init($scriptProperties);
        $gateway->refreshContextCache($partitions);
        break;
    case 'OnHandleRequest':
        //$modx->log(modX::LOG_LEVEL_ERROR, "OnHandleRequest fired on {$_SERVER['REQUEST_URI']}");
        if ($modx->context->get('key') == 'mgr') break;
        $disabled = $modx->getOption('disable_router', $scriptProperties, false);
        if ($disabled) {
            break;
        } else {
            $gateway->init($scriptProperties);
            $gateway->handleRequest();
            break;            
        }
    default:
        break;
}
