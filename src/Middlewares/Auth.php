<?php
namespace Mvc\Middlewares;

use Mvc\App;
use Mvc\Events\Event;
use Mvc\Exception;
use Mvc\Http\Request;
use Mvc\Lib\Auth as UserAuth;
use Mvc\Lib\Company;
use Mvc\Lib\Lang;
use Mvc\Models\Companies;
use Mvc\Models\Settings;
use Mvc\Models\Users;

class Auth extends \Mvc\Middleware
{
    /**
     * beforeExecuteRoute
     *
     * @param  Event $event
     * @param  App $app
     * @return void
     */
    public function beforeExecuteRoute(Event $event, App $app)
    {
        $lang = isset($_COOKIE['lang']) ? (string) trim($_COOKIE['lang']) : (string) trim(Request::get('lang'));
        if (preg_match('/[a-z]{2}/i', trim($lang))) {
            Lang::setLang($lang);
        }

        $token = isset($_COOKIE['ut']) ? (string) trim($_COOKIE['ut']) : (string) trim(Request::get('token'));
        UserAuth::setToken($token);

        $data = Settings::init();
        if (is_array($data)) {
            Settings::setData($data);
            foreach ($data as $key => $value) {
                switch ($key) {
                    case 'account_error':
                        throw new Exception($value['description'], $value['error_code']);

                    case 'account':
                        if ($value['id']) {
                            UserAuth::setData(new Users($value));
                        }
                        break;

                    case 'permissions':
                        UserAuth::setPermissions($value);
                        break;

                    case 'company':
                        if ($value['id']) {
                            Company::setData(new Companies($value));
                        }
                        break;

                    case 'translations':
                        Lang::setData($value);
                        break;
                }
            }
        }
    }
}
