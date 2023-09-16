<?php
namespace Mvc;

use Mvc\AppInterface;
use Mvc\Exception;
use Mvc\MiddlewareInterface;
use Mvc\Router;

class App implements AppInterface
{
    /**
     * di
     *
     * @var AppInterface
     */
    public static $di;

    /**
     * modules
     *
     * @var array
     * @access protected
     */
    protected $modules = [];

    /**
     * services
     *
     * @var array
     * @access protected
     */
    protected $services = [];

    /**
     * eventListeners
     *
     * @var array
     * @access protected
     */
    protected $eventListeners = [];

    /**
     * defaultNamespace
     *
     * @var null|string
     * @access protected
     */
    protected $defaultNamespace;

    /**
     * controllerSuffix
     *
     * @var string
     * @access protected
     */
    protected $controllerSuffix = 'Controller';

    /**
     * actionSuffix
     *
     * @var string
     * @access protected
     */
    protected $actionSuffix = 'Action';

    /**
     * Events Manager
     *
     * @var null|\Mvc\Events\ManagerInterface
     * @access protected
     */
    protected $eventsManager;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        self::$di = $this;
    }

    /**
     * initialize
     *
     * @param  callable $callback
     * @return void
     */
    public function initialize($callback)
    {
        \call_user_func($callback);
    }

    /**
     * Sets the events manager
     *
     * @param \Mvc\Events\Manager $eventsManager
     */
    public function setEventsManager($eventsManager)
    {
        if (!is_object($eventsManager)) {
            throw new Exception('Invalid parameter type.');
        }

        $this->eventsManager = $eventsManager;
    }

    /**
     * Returns the internal event manager
     *
     * @return null|\Mvc\Events\Manager
     */
    public function getEventsManager()
    {
        return $this->eventsManager;
    }

    /**
     * setControllerSuffix
     *
     * @param  null|string $value
     * @return void
     */
    public function setControllerSuffix($value)
    {
        $this->controllerSuffix = (string) $value;
        return $this;
    }

    /**
     * setActionSuffix
     *
     * @param  null|string $value
     * @return void
     */
    public function setActionSuffix($value)
    {
        $this->actionSuffix = (string) $value;
        return $this;
    }

    /**
     * setDefaultNamespace
     *
     * @param  string $namespace
     * @return void
     */
    public function setDefaultNamespace($namespace)
    {
        $this->defaultNamespace = $namespace;
        return $this;
    }

    /**
     * addEvent
     *
     * @param  callable $callback
     * @return void
     */
    public function addEvent($callback)
    {
        $this->eventListeners[] = $callback;
        return $this;
    }

    /**
     * addMiddleware
     *
     * @param  callable $callback
     * @return void
     */
    public function addMiddleware($callback)
    {
        if (!$callback instanceof MiddlewareInterface) {
            throw new Exception('Invalid middleware type.');
        }

        $this->addEvent($callback);
        return $this;
    }

    /**
     * registerServices
     *
     * @param  array $services
     * @return void
     */
    public function registerServices($services = [])
    {
        foreach ($services as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    /**
     * setService
     *
     * @param  string $property
     * @param  callable $callback
     * @return void
     */
    public function set($property, $callback)
    {
        $this->services[$property] = $callback;
        return $this;
    }

    /**
     * get
     *
     * @param  string $property
     * @return mixed
     */
    public function get($property)
    {
        $service = $this->services[$property];
        if (\is_callable($service)) {
            $service = \call_user_func($service, $this);
        }
        return $service;
    }

    /**
     * has
     *
     * @param  string $property
     * @return bool
     */
    public function has($property)
    {
        $service = $this->services[$property];
        if (\is_callable($service)) {
            return true;
        }
        return false;
    }

    /**
     * __get
     *
     * @param  string $property
     * @return mixed
     */
    public function __get($property)
    {
        if (!\property_exists($this, $property)) {
            if (\array_key_exists($property, $this->services)) {
                return $this->get($property);
            }
            throw new Exception("Property {$property} does not exists");
        }
        return $this->{$property};
    }

    /**
     * registerModules
     *
     * @param  array $modules
     * @return void
     */
    public function registerModules($modules = [])
    {
        $this->modules = $modules;
        return $this;
    }

    /**
     * handle
     *
     * @param  string|null $uri
     * @return mixed
     */
    public function handle($uri = null)
    {
        $router = $this->get('router');
        if (!$router instanceof Router) {
            $exception = new Exception('Router service was not registered');

            if (is_object($this->eventsManager)) {
                if ($this->eventsManager->fire('dispatch:beforeException', $this, $exception) === false) {
                    return false;
                }
            }

            throw $exception;
        }

        $router->handle($uri);
        $this->set('router', $router);

        if ($this->modules) {
            if (isset($this->modules[$router->getModuleName()])) {
                $module = $this->modules[$router->getModuleName()];
                if (!\file_exists($module['path'])) {
                    $exception = new Exception('Module ' . $module['path'] . ' not found', Exception::ERROR_NOT_FOUND_MODULE);

                    if (is_object($this->eventsManager)) {
                        if ($this->eventsManager->fire('dispatch:beforeException', $this, $exception) === false) {
                            return false;
                        }
                    }

                    throw $exception;
                }

                include $module['path'];
                $moduleNamespace = "\\" . $module['className'];

                if (!\class_exists($moduleNamespace)) {
                    $exception = new Exception('' . $moduleNamespace . ' class not found in ' . $module['path'] . '', Exception::ERROR_NOT_FOUND_MODULE);

                    if (is_object($this->eventsManager)) {
                        if ($this->eventsManager->fire('dispatch:beforeException', $this, $exception) === false) {
                            return false;
                        }
                    }

                    throw $exception;
                }

                $moduleClass = new $moduleNamespace();
                if (!\method_exists($moduleClass, 'register')) {
                    $exception = new Exception('register method not found in ' . $moduleNamespace . ' class');

                    if (is_object($this->eventsManager)) {
                        if ($this->eventsManager->fire('dispatch:beforeException', $this, $exception) === false) {
                            return false;
                        }
                    }

                    throw $exception;
                }

                $moduleClass->register($this);
            }
        }

        $controllerName      = \ucfirst($router->getControllerName()) . $this->controllerSuffix;
        $controllerNamespace = "\\" . $this->defaultNamespace . "\\" . $controllerName;

        if (!\class_exists($controllerNamespace)) {
            $exception = new Exception('' . $controllerNamespace . ' class not found', Exception::ERROR_NOT_FOUND_CONTROLLER);

            if (is_object($this->eventsManager)) {
                if ($this->eventsManager->fire('dispatch:beforeException', $this, $exception) === false) {
                    return false;
                }
            }

            throw $exception;
        }

        $actionName = $router->getActionName() . $this->actionSuffix;
        if (!\method_exists($controllerNamespace, $actionName)) {
            $exception = new Exception('' . $actionName . ' method not found in ' . $controllerNamespace . '', Exception::ERROR_NOT_FOUND_ACTION);

            if (is_object($this->eventsManager)) {
                if ($this->eventsManager->fire('dispatch:beforeException', $this, $exception) === false) {
                    return false;
                }
            }

            throw $exception;
        }

        if (is_object($this->eventsManager)) {
            $this->eventsManager->fire('dispatch:beforeExecuteRoute', $this);
        }

        $controller = new $controllerNamespace($this);
        $handler    = \call_user_func_array([$controller, $actionName], $router->getParams());

        return $handler;
    }
}
