<?php

namespace greenmeteor\native;

use greenmeteor\native\bridge\NativeBridge;
use greenmeteor\native\plugin\PluginManager;
use greenmeteor\native\renderer\Renderer;
use greenmeteor\native\runtime\Runtime;
use yii\base\Component;

class Native extends Component
{
    public $runtimeClass = Runtime::class;
    public $bridgeClass = NativeBridge::class;
    public $rendererClass = Renderer::class;
    public $pluginManagerClass = PluginManager::class;

    private $_runtime;
    private $_bridge;
    private $_renderer;
    private $_pluginManager;

    public function init()
    {
        parent::init();

        $this->_runtime = Yii::createObject([
            'class' => $this->runtimeClass,
        ]);

        $this->_bridge = Yii::createObject([
            'class' => $this->bridgeClass,
        ]);

        $this->_renderer = Yii::createObject([
            'class' => $this->rendererClass,
        ]);

        $this->_pluginManager = Yii::createObject([
            'class' => $this->pluginManagerClass,
        ]);
    }

    public function getRuntime(): Runtime
    {
        return $this->_runtime;
    }

    public function getBridge(): NativeBridge
    {
        return $this->_bridge;
    }

    public function getRenderer(): Renderer
    {
        return $this->_renderer;
    }

    public function getPluginManager(): PluginManager
    {
        return $this->_pluginManager;
    }

    public function isAvailable(): bool
    {
        return $this->_runtime->isAvailable();
    }

    public function call(string $function, array $arguments = [])
    {
        return $this->_bridge->call($function, $arguments);
    }
}
