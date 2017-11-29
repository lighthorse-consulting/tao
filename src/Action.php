<?php
/**
 * Tao Action class
 *
 * Copyright (c) 2016-2017 LightHorse Consulting, LLC. All rights reserved.
 * Distributed under the MIT license.
 */
namespace Tao;

use Katana\Sdk\Action as BaseAction;
use Tao\Plugin\ActionPlugin;

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
     * @var ActionPlugin[]
     */
    private $plugins = [];


    /**
     * INI settings.
     *
     * @var array
     */
    protected $settings = null;

    /**
     * Action instance.
     *
     * @var \Katana\Sdk\Action
     */
    protected $action = null;

    /**
     * Database instance.
     *
     * @var \PDO
     */
    protected $database = null;

    /**
     * Constructor.
     *
     * @param \Katana\Sdk\Action $action The action instance.
     */
    public function __construct(BaseAction &$action)
    {
        $this->action = $action;
        $this->action->log('[TAO] Service path: ' . dirname($_SERVER['SCRIPT_NAME']));
        $path = dirname($_SERVER['SCRIPT_NAME']) . DIRECTORY_SEPARATOR;
        $this->action->log('[TAO] Parsing settings.ini file...');
        $this->settings = parse_ini_file($path . 'settings.ini', true);
        if (is_readable($path . 'settings.local.ini')) {
            $this->action->log('[TAO] Parsing settings.local.ini file...');
            $settings = parse_ini_file($path . 'settings.local.ini', true);
            $this->settings = array_merge($this->settings, $settings);
        }
    }

    /**
     * Returns the path for an action source file.
     *
     * @param string $filename The filename to lookup.
     * @return string
     */
    public function getSourcePath($filename)
    {
        $path = implode(DIRECTORY_SEPARATOR, [
            dirname($_SERVER['SCRIPT_NAME']),
            'actions',
            "{$filename}.php"
        ]);
        $this->action->log("[TAO] Source file path resolved: {$path}");
        if (!is_readable($path)) {
            $this->error('Source file not readable', 1);
            return '';
        }
        return $path;
    }

    /**
     * Initializes an instance.
     *
     * @param \Katana\Sdk\Action $action The action instance.
     * @return \Tao\Action
     */
    public static function init(BaseAction &$action)
    {
        $action->log('[TAO] Initializing...');
        return new static($action);
    }

    /**
     * Loads a source file relatively from "actions/". If the filename is not 
     * provided it assumes the action name.
     *
     * @param string $filename The file to load, without the ".php" extension. 
     * @return \Tao\Action
     */
    public function load($filename = null)
    {
        if (!$filename) {
            $filename = $this->action->getActionName();
        }
        $_file = $this->getSourcePath($filename);
        $_this = $this;
        $this->action->log("[TAO] Action file: {$_file}");
        $callback = function (&$action, $settings, $database) use ($_file, $_this) {
            try {
                $action->log('[TAO] Loading action file...');
                include $_file;
            } catch(\Exception $e) {
                $_this->error($e->getMessage(), $e->getCode());
            }
            $_this->action($action);
        };
        $callback($this->action, $this->settings, $this->database());
        return $this;
    }

    /**
     * Loads a source file relatively from "actions/". If the filename is not
     * provided it assumes the action name.
     *
     * @param callable $callback The callable to call.
     * @return \Tao\Action
     */
    public function call(callable $callback = null)
    {
        $this->action->log("[TAO] Action callback: {$this->action->getActionName()}");
        try {
            if (!$callback) {
                $path = $this->getSourcePath($this->action->getActionName());
                if ($path) {
                    $action->log('[TAO] Loading action callback...');
                    $callback = require $path;
                } else {
                    return $this;
                }
            }
            $this->action->log("[TAO] Executing callback...");
            $callback($this);
        } catch(\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        return $this;
    }

    /**
     * Reads a settings section and optional property.
     *
     * @param string $section The section of the settings file.
     * @param string $property The optional property name.
     * @return string
     */
    public function setting($section, $property = null)
    {
        if (!isset($this->settings[$section])) {
            return null;
        }
        if (isset($property)) {
            if (!isset($this->settings[$section][$property])) {
                return null;
            }
            return $this->settings[$section][$property];
        }
        return $this->settings[$section];
    }

    /**
     * Gets the action instance.
     *
     * @param \Katana\Sdk\Action $action An action instance to update the value.
     * @return \Katana\Sdk\Action
     */
    public function action(BaseAction &$action = null)
    {
        if (isset($action)) {
            $this->action = $action;
        }
        return $this->action;
    }

    /**
     * Run and return the action instance.
     *
     * @return \Katana\Sdk\Action
     */
    public function run()
    {
        return $this->action();
    }

    /**
     * Initializes and gets the database.
     *
     * @return \PDO
     */
    public function database()
    {
        if (!$this->database && isset($this->settings['database'])) {
            $this->action->log('[TAO] Connecting to database: ' . $this->settings['database']['dsn']);
            $this->action->log('[TAO] Accessing with user: ' . $this->settings['database']['username']);
            $this->database = new \PDO(
                $this->settings['database']['dsn'],
                $this->settings['database']['username'],
                $this->settings['database']['password'],
                [
                    //\PDO::ATTR_PERSISTENT => true,
                    \PDO::ATTR_TIMEOUT => 10,
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
    public function query($sql)
    {
        $this->action->log("[TAO] Running query: {$sql}");
        return $this->database()->query($sql, \PDO::FETCH_ASSOC)->fetchAll();
    }

    /**
     * Resolves parameters to an string of SQL function parameters.
     *
     * @param string $location The optional location of the parameters.
     * @return string
     */
    public function params($location = null)
    {
        $params = [];
        foreach ($this->action->getParams($location) as $param) {
            switch ($param->getType()) {
                case 'null':
                    $value = 'NULL';
                    break;
                case 'boolean':
                    $value = !$param->getValue() ? 'FALSE' : 'TRUE';
                    break;
                case 'string':
                    $value = $this->database()->quote($param->getValue());
                    break;
                case 'array':
                    $value = $this->database()->quote($param->getValue());
                    break;
                case 'object':
                    $value = $this->database()->quote($param->getValue());
                    break;
                default:
                    $value = $param->getValue();
            }
            $params[] = "p_{$param->getName()} := {$value}";
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
     * @return \Tao\Action
     */
    public function entity($entity, $params = true)
    {
        $this->action->log('[TAO] Adding entity...');
        try {
            if (is_string($entity)) {
                if ($params) {
                    if (is_array($params)) {
                        $items = [];
                        foreach ($params as $param => $value) {
                            $value = is_string($value) ? $this->database()->quote($value) : $value;
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
                $entity = is_array($entity) && isset($entity[0]) ? $entity[0] : [];
            }
            $this->action->setEntity((array)$entity);
        } catch(\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
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
     * @return \Tao\Action
     */
    public function collection($collection, $params = true)
    {
        $this->action->log('[TAO] Adding collection...');
        try {
            if (is_string($collection)) {
                if ($params) {
                    if (is_array($params)) {
                        $items = [];
                        foreach ($params as $param => $value) {
                            $value = is_string($value) ? $this->database()->quote($value) : $value;
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
                $collection = is_array($collection) ? $collection : [];
            }
            $this->action->setCollection((array)$collection);
        } catch(\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        return $this;
    }

    /**
     * Registers a relation.
     *
     * @param string $pk The primary key.
     * @param string $type The related type.
     * @param string|array $fk The foreign key(s) to relate.
     * @return \Tao\Action
     */
    public function relation($pk, $type, $fk)
    {
        $this->action->log("[TAO] Adding relation: {$type} ({$pk})");
        try {
            if (is_array($fk)) {
                $this->action->relateMany($pk, $type, $fk);
            } else {
                $this->action->relateOne($pk, $type, $fk);
            }
        } catch(\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        return $this;
    }

    /**
     * Registers a hypelink.
     *
     * @param string $link The link reference.
     * @param string $uri The link URI.
     * @return \Tao\Action
     */
    public function link($link, $uri)
    {
        $this->action->log("[TAO] Adding link: {$link} ({$uri})");
        try {
            $this->action->link($link, $uri);
        } catch(\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        return $this;
    }

    /**
     * Registers an error in the transport.
     *
     * @param string $message The error message.
     * @param integer $code The error code, defaults to 0.
     * @param string $status The HTTP status code, default to ERROR_STATUS.
     * @return \Tao\Action
     */
    public function error($message, $code = 0, $status = self::ERROR_STATUS)
    {
        $this->action->log("[TAO] Error: {$message}");
        try {
            $this->action->error($message, (int) $code, $status);
        } catch(\Exception $e) {
            $this->action->error($e->getMessage(), (int) $e->getCode(), '500 Internal Server Error');
        }
        return $this;
    }

    /**
     * @param string $name
     * @param array ...$args
     * @return $this
     * @throws \Exception
     */
    public function plugin(string $name, ...$args)
    {
        if (!isset($this->plugins[$name])) {
            $pluginName = ucfirst($name);
            $pluginClass = "Tao\\Plugin\\$pluginName";
            if (!class_exists($pluginClass)) {
                throw new \Exception("Plugin $pluginName not found");
            }

            $plugin = new $pluginClass($this);
            if (!$plugin instanceof ActionPlugin) {
                throw new \Exception("Invalid plugin. Must implement Tao\Plugin\ActionPlugin");
            }

            $this->plugins[$name] = $plugin;

        } else {
            $plugin = $this->plugins[$name];
        }

        $plugin->run(...$args);

        return $this;
    }
}
