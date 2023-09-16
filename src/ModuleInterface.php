<?php
namespace Mvc;

use Mvc\App;

interface ModuleInterface
{    
    /**
     * register
     *
     * @param  App $app
     * @return void
     */
    public function register(App $app);
}
