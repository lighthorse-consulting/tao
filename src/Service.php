<?php
/**
 * Tao Service class
 *
 * Copyright (c) 2016-2017 LightHorse Consulting, LLC. All rights reserved.
 * Distributed under the MIT license.
 */
namespace Tao;

use Katana\Sdk\Service as BaseService;

/**
 * Service class.
 */
class Service
{
    /**
     * Service instance.
     *
     * @var \Katana\Sdk\Service
     */
    protected $service = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->service = new BaseService;
    }

    /**
     * Gets the service instance.
     *
     * @return \Katana\Sdk\Service
     */
    public function service()
    {
        return $this->service;
    }

    /**
     * Initializes an instance.
     *
     * @static
     * @param array $actions The key => value array of actions to register, 
     * where the key is the action name and the value a callback.
     * @return \Tao\Service
     */
    public static function init($actions)
    {
        $instance = new static;
        $service = $instance->service();
        $service->action('status', function ($action) {
            return Action::init($action)->entity([
                'status' => 'OK',
                'service' => $action->getName(),
                'version' => $action->getVersion(),
                'time' => date('Y-m-d H:i:s')
            ])->run();
        });
        foreach ($actions as $action => $callback) {
            $service->action($action, $callback);
        }
        return $instance;
    }

    /**
     * Registers a startup event.
     *
     * @param callable $callback The function to execute.
     * @return \Tao\Service
     */
    public function startup(callable $callback)
    {
        $this->service->startup($callback);
        return $this;
    }

    /**
     * Registers a shutdown event.
     *
     * @param callable $callback The function to execute.
     * @return \Tao\Service
     */
    public function shutdown(callable $callback)
    {
        $this->service->shutdown($callback);
        return $this;
    }

    /**
     * Runs the Service instance.
     *
     * @return boolean
     */
    public function run()
    {
        return $this->service->run();
    }
}
