<?php

namespace Tao\Plugin;

use Tao\Action;

abstract class ActionPlugin
{
    /**
     * @var Action
     */
    private $action;

    /**
     * @param Action $action
     */
    public function __construct(Action $action)
    {
        $this->action = $action;
    }

    /**
     * @return Action
     */
    public function getAction(): Action
    {
        return $this->action;
    }
}
