<?php
/**
 * Tao Action class
 *
 * Copyright (c) 2016-2017 LightHorse Consulting, LLC. All rights reserved.
 * Distributed under the MIT license.
 */
namespace Tao;

use Katana\Sdk\Action as BaseAction;

/**
 * Action class.
 */
class Action
{
    /**
     * Default error status.
     */
    const ERROR_STATUS = '500 Internal Server Error';

    /**
     * INI settings.
     *
     * @var array
     */
    protected $settings = null;

    /**
     * Action instance.
     *
     * @var Katana\Sdk\Action
     */
    protected $action = null;

    /**
     * Database instance.
     *
     * @var PDO
     */
    protected $database = null;

    /**
     * Constructor.
     *
     * @param Katana\Sdk\Action $action The action instance.
     */
    public function __construct(BaseAction $action)
    {
        $path = dirname($_SERVER['SCRIPT_NAME']) . DIRECTORY_SEPARATOR;
        $this->settings = parse_ini_file($path . 'settings.ini', true);
        if (is_readable($path . 'settings.local.ini')) {
            $settings = parse_ini_file($path . 'settings.local.ini', true);
            $this->settings = array_merge($this->settings, $settings);
        }
        $this->action = $action;
    }

    /**
     * Initializes an instance.
     *
     * @param Katana\Sdk\Action $action The action instance.
     * @return Tao\Action
     */
    public static function init(BaseAction $action)
    {
        return new static($action);
    }

    /**
     * Gets the action instance.
     *
     * @return Katana\Sdk\Action
     */
    public function action()
    {
        return $this->action;
    }

    /**
     * Run and return the action instance.
     *
     * @return Katana\Sdk\Action
     */
    public function run()
    {
        return $this->action();
    }

    /**
     * Initializes and gets the database.
     *
     * @return PDO
     */
    protected function database()
    {
        if (!$this->database) {
            $this->database = new \PDO(
                $this->settings['database']['dsn'],
                $this->settings['database']['username'],
                $this->settings['database']['password'],
                [
                    \PDO::ATTR_PERSISTENT => true,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                ]
            );
        }
        return $this->database;
    }

    /**
     * Executes an SQL query against the database.
     *
     * @param string $sql The SQL query to execute.
     * @return array
     */
    protected function query($sql)
    {
        return $this->database()->query($sql, \PDO::FETCH_ASSOC)->fetchAll();
    }

    /**
     * Resolves parameters to an string of SQL function parameters.
     *
     * @param string $location The optional location of the parameters.
     * @return string
     */
    protected function params($location = null)
    {
        $params = [];
        foreach ($this->action->getParams($location) as $param) {
            $params[] = "p_{$param->getName()} := {$param->getValue()}";
        }
        return implode(', ', $params);
    }

    /**
     * Add an entity as the transport data.
     *
     * @param array|string $entity The entity object, or if a string, the SQL 
     * query to execute and return an entity.
     * @param boolean|array $params If true all parameters will be passed to 
     * the SQL function as parameters, if false no parameters will be passed, 
     * if a string then the parameters from that location will be passed, and 
     * if an array the items will be assumed as key => value.
     * @return Tao\Action
     */
    public function entity($entity, $params = true)
    {
        try {
            if (is_string($entity)) {
                if ($params) {
                    if (is_array($params)) {
                        $items = [];
                        foreach ($params as $param => $value) {
                            $items[] = "p_{$param} := {$value}";
                        }
                        $params = implode(', ', $items);
                    } else {
                        $params = is_string($params) ? $this->params($params) : $this->params();
                    }
                    $entity = $this->query("SELECT * FROM {$entity}({$params})");
                } else {
                    $entity = $this->query("SELECT * FROM {$entity}()");
                }
                $entity = $entity[0];
            }
            $this->action->setEntity($entity);
        } catch(\Exception $e) {
            $this->action->error($e->getMessage(), $e->getCode());
        }
        return $this;
    }

    /**
     * Add a collection as the transport data.
     *
     * @param array|string $collection The collection array, or if a string, 
     * the SQL function to call and return a collection.
     * @param boolean|array $params If true all parameters will be passed to 
     * the SQL function as parameters, if false no parameters will be passed, 
     * if a string then the parameters from that location will be passed, and 
     * if an array the items will be assumed as key => value.
     * @return Tao\Action
     */
    public function collection($collection, $params = true)
    {
        try {
            if (is_string($collection)) {
                if ($params) {
                    if (is_array($params)) {
                        $items = [];
                        foreach ($params as $param => $value) {
                            $items[] = "p_{$param} := {$value}";
                        }
                        $params = implode(', ', $items);
                    } else {
                        $params = is_string($params) ? $this->params($params) : $this->params();
                    }
                    $collection = $this->query("SELECT * FROM {$collection}({$params})");
                } else {
                    $collection = $this->query("SELECT * FROM {$collection}()");
                }
            }
            $this->action->setCollection($collection);
        } catch(\Exception $e) {
            $this->action->error($e->getMessage(), $e->getCode());
        }
        return $this;
    }

    /**
     * Registers a relation.
     *
     * @param string $pk The primary key.
     * @param string $type The related type.
     * @param string|array $fk The foreign key(s) to relate.
     * @return Tao\Action
     */
    public function relation($pk, $type, $fk)
    {
        try {
            if (is_array($fk)) {
                $this->action->relateMany($pk, $type, $fk);
            } else {
                $this->action->relateOne($pk, $type, $fk);
            }
        } catch(\Exception $e) {
            $this->action->error($e->getMessage(), $e->getCode());
        }
        return $this;
    }

    /**
     * Registers a hypelink.
     *
     * @param string $link The link reference.
     * @param string $uri The link URI.
     * @return Tao\Action
     */
    public function link($link, $uri)
    {
        try {
            $this->action->link($link, $uri);
        } catch(\Exception $e) {
            $this->action->error($e->getMessage(), $e->getCode());
        }
        return $this;
    }

    /**
     * Registers an error in the transport.
     *
     * @param string $message The error message.
     * @param integer $code The error code, defaults to 0.
     * @param string $status The HTTP status code, default to ERROR_STATUS.
     * @return Tao\Action
     */
    public function error($message, $code = 0, $status = self::ERROR_STATUS)
    {
        try {
            $this->action->error($message, $code, $status);
        } catch(\Exception $e) {
            $this->action->error($e->getMessage(), $e->getCode());
        }
        return $this;
    }
}
