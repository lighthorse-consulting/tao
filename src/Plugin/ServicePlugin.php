<?php

namespace Tao\Plugin;

use Tao\Service;

abstract class ServicePlugin implements PluginInterface
{
    /**
     * @var Service
     */
    private $service;

    /**
     * @param Service $service
     */
    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    /**
     * @return Service
     */
    protected function getService(): Service
    {
        return $this->service;
    }
}
