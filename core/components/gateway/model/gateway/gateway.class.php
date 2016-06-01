<?php

class Gateway {
    /** @var modX $modx */
    private $modx;

    private $skipSettings = ['ctx_parent', 'base_url', 'site_url', 'ctx_alias', 'base_href'];
    private $scriptProperties;

    /** @var xPDOCache $contextCache */
    private $contextCache;
    private $pieces;
    private $cacheKey;
    
    /** options **/
    public $skipWebCtx = true;
    
    public function __construct(modX &$modx) {
        $this->modx =& $modx;

        $this->skipSettings = array_keys($this->skipSettings);
    }

    public function init($scriptProperties) {
        $this->scriptProperties = $scriptProperties;

        $this->cacheKey = $this->modx->getOption('cache_system_settings_key', $this->scriptProperties, 'system_settings');
        $this->skipWebCtx = $this->modx->getOption('skip_web_ctx', $this->scriptProperties, true, true);
    }
    
    public function getContexts() {
        $contexts = array();

        $this->loadContextCache();

        if ($this->contextCache) {
            $contexts = $this->contextCache->get('context_map');
        }

        if (empty($contexts)) {
            $contexts = $this->cacheContextsAndSettings();
        }
        
        return $contexts;
    }

    public function handleRequest() {
        $contexts = $this->getContexts();

        if (empty($contexts)) return;

        $this->pieces = explode('/', trim($_REQUEST[$this->modx->getOption('request_param_alias', null, 'q')], ' '), 3);

        if (count($this->pieces) == 0 || (count($this->pieces) == 1 && $this->pieces[0] == '')) return;

        $this->processRequest($contexts);
    }

    public function refreshContextCache($partitions) {
        /** @var modX $modx */

        if (!empty($partitions) && array_key_exists($this->cacheKey, $partitions)) {
            $this->loadContextCache();

            if ($this->contextCache) {
                $this->cacheContextsAndSettings();
            } else {
                $this->modx->log(modX::LOG_LEVEL_ERROR, "Could not get context_map cache partition with key {$this->cacheKey}");
            }
        } else {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "Cache partition with key {$this->cacheKey} was not included in refresh() request; context_map not updated");
        }
    }

    private function loadContextCache(){
        $cacheOptions = array(
            xPDO::OPT_CACHE_HANDLER => $this->modx->getOption("cache_{$this->cacheKey}_handler", $this->scriptProperties, $this->modx->getOption(xPDO::OPT_CACHE_HANDLER)),
            xPDO::OPT_CACHE_EXPIRES => $this->modx->getOption("cache_{$this->cacheKey}_expires", $this->scriptProperties, $this->modx->getOption(xPDO::OPT_CACHE_EXPIRES)),
        );

        $this->contextCache = $this->modx->cacheManager->getCacheProvider($this->cacheKey, $cacheOptions);
    }

    private function cacheContextsAndSettings() {
        $contexts = array();
        $protectedContexts = array('mgr');
        if ($this->skipWebCtx) $protectedContexts[] = 'web';
        /** @var modContext $contextsGraph */
        $query = $this->modx->newQuery('modContext');
        $query->where(array('modContext.key:NOT IN' => $protectedContexts));
        $query->sortby($this->modx->escape('modContext') . '.' . $this->modx->escape('key'), 'ASC');
        $contextsGraph = $this->modx->getCollectionGraph('modContext', '{"ContextSettings":{}}', $query);

        foreach ($contextsGraph as $context) {
            $contextSettings = [];

            foreach ($context->ContextSettings as $cSetting) {
                $contextSettings[$cSetting->get('key')] = $cSetting->get('value');
            }

            $contexts[$context->get('key')] = $contextSettings;
            
        }

        unset($contextsGraph);

        if ($this->contextCache) {
            $this->contextCache->set('context_map', $contexts);
        }

        return $contexts;
    }

    private function setPlaceholdersAndOptions($contexts, $cSettings) {
        if (!isset($cSettings['ctx_parent'])) return;
        foreach ($contexts[$cSettings['ctx_parent']] as $key => $value) {
            if (isset($this->skipSettings[$key])) continue;

            $this->modx->setPlaceholder('+' . $key, $value);
            $this->modx->setOption($key, $value);
        }
    }

    private function processRequest($contexts) {
        $parent = null;
        foreach($contexts as $cKey => $cSettings) {
            if (isset($this->pieces[1]) && ($this->pieces[1] == $cSettings['ctx_alias']) && isset($cSettings['ctx_parent']) && isset($contexts[$cSettings['ctx_parent']]) && ($this->pieces[0] == $contexts[$cSettings['ctx_parent']]['ctx_alias'])) {
                return $this->processChildContext($contexts, $cKey, $cSettings);
            }

            if ($this->pieces[0] == $cSettings['ctx_alias']) {
                $parent = array('cKey' => $cKey, 'cSettings' => $cSettings);
            }
        }
        
        if ($parent !== null) {
            return $this->processParentContext($contexts, $parent['cKey'], $parent['cSettings']);
        }

        return false;
    }

    private function processChildContext($contexts, $cKey, $cSettings) {
        $this->handleRedirect(2, $cSettings['site_start']);

        $this->setPlaceholdersAndOptions($contexts, $cSettings);

        $this->modx->switchContext($cKey);
        $this->modx->log(modX::LOG_LEVEL_DEBUG, "Switched to context {$cKey} from URI {$_REQUEST['q']}");

        return true;
    }

    private function processParentContext($contexts, $cKey, $cSettings) {
        if (isset($cSettings['ctx_parent']) && isset($contexts[$cSettings['ctx_parent']])) {
            $this->redirectToParentChildURI($contexts, $cSettings);
        }

        $this->handleRedirect(1, $cSettings['site_start']);

        $this->setPlaceholdersAndOptions($contexts, $cSettings);

        $this->modx->switchContext($cKey);
        $this->modx->log(modX::LOG_LEVEL_DEBUG, "Switched to context {$cKey} from URI {$_REQUEST['q']}");

        return true;
    }

    private function handleRedirect($position, $siteStart) {
        if (isset($this->pieces[$position])) {
            $this->pieces = array_slice($this->pieces, $position);
            $siteStart = $this->modx->getObject('modResource', $siteStart);
            if ($this->pieces[count($this->pieces) - 1] == '') {
                array_pop($this->pieces);
            }
            $pieces = implode('/', $this->pieces);
            
            $q = ($pieces == '') ? $siteStart->alias : $pieces;

            $_REQUEST[$this->modx->getOption('request_param_alias', null, 'q')] = $q;
        } else {
            $this->modx->sendRedirect(MODX_SITE_URL . str_replace('//', '/', implode('/', $this->pieces) . '/'), ['responseCode' => 'HTTP/1.1 301 Moved Permanently']);
        }
    }

    private function redirectToParentChildURI($contexts, $cSettings){
        array_unshift($this->pieces, $contexts[$cSettings['ctx_parent']]['ctx_alias']);
        $this->modx->sendRedirect(MODX_SITE_URL . str_replace('//', '/', implode('/', $this->pieces) . '/'), ['responseCode' => 'HTTP/1.1 301 Moved Permanently']);
    }


}
