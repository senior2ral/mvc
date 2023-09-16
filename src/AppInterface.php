<?php
namespace Mvc;

interface AppInterface
{    
    /**
     * initialize
     *
     * @param  callable $callback
     * @return void
     */
    public function initialize($callback);

    /**
     * setControllerSuffix
     *
     * @param  null|string $value
     * @return void
     */
    public function setControllerSuffix($value);

    /**
     * setActionSuffix
     *
     * @param  null|string $value
     * @return void
     */
    public function setActionSuffix($value);

    /**
     * setDefaultNamespace
     *
     * @param  string $namespace
     * @return void
     */
    public function setDefaultNamespace($namespace);

    /**
     * addEvent
     *
     * @param  callable $callback
     * @return void
     */
    public function addEvent($callback);

    /**
     * addMiddleware
     *
     * @param  callable $callback
     * @return void
     */
    public function addMiddleware($callback);

    /**
     * registerServices
     *
     * @param  array $services
     * @return void
     */
    public function registerServices($services = []);

    /**
     * setService
     *
     * @param  string $property
     * @param  callable $callback
     * @return void
     */
    public function set($property, $callback);

    /**
     * getService
     *
     * @param  string $property
     * @return mixed
     */
    public function get($property);

    /**
     * has
     *
     * @param  string $property
     * @return bool
     */
    public function has($property);

    /**
     * registerModules
     *
     * @param  array $modules
     * @return void
     */
    public function registerModules($modules = []);

    /**
     * handle
     *
     * @param  null|string $uri
     * @return void
     */
    public function handle($uri = null);
}
