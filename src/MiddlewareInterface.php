<?php
namespace Mvc;

use Mvc\App;
use Mvc\Events\Event;
use Mvc\Exception;

interface MiddlewareInterface
{
     /**
     * beforeExecuteRoute
     *
     * @param  Event $event
     * @param  App $app
     * @return void
     */
    public function beforeExecuteRoute(Event $event, App $app);

    /**
     * beforeException
     *
     * @param  Event $event
     * @param  App $app
     * @param  Exception $exception
     * @return void
     */
    public function beforeException(Event $event, App $app, Exception $exception);
}
