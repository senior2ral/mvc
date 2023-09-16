<?php
namespace Mvc\Http;

use Mvc\App;
use Mvc\CryptInterface;
use Mvc\Exception;
use Mvc\FilterInterface;
use Mvc\Session\AdapterInterface as SessionInterface;

/**
 * Mvc\Http\Cookie
 *
 * Provide OO wrappers to manage a HTTP cookie
 */
class Cookie
{
    /**
     * Readed
     *
     * @var boolean
     * @access protected
     */
    protected $_readed = false;

    /**
     * Restored
     *
     * @var boolean
     * @access protected
     */
    protected $_restored = false;

    /**
     * Use Encryption?
     *
     * @var boolean
     * @access protected
     */
    protected $_useEncryption = false;

    /**
     * Filter
     *
     * @var null|\Mvc\FilterInterface
     * @access protected
     */
    protected $_filter;

    /**
     * Name
     *
     * @var null|string
     * @access protected
     */
    protected $_name;

    /**
     * Value
     *
     * @var null|string
     * @access protected
     */
    protected $_value;

    /**
     * Expire
     *
     * @var null|int
     * @access protected
     */
    protected $_expire;

    /**
     * Path
     *
     * @var string
     * @access protected
     */
    protected $_path = '/';

    /**
     * Domain
     *
     * @var null|string
     * @access protected
     */
    protected $_domain;

    /**
     * Secure
     *
     * @var null|boolean
     * @access protected
     */
    protected $_secure;

    /**
     * HTTP Only?
     *
     * @var boolean
     * @access protected
     */
    protected $_httpOnly = true;

    /**
     * Same Site
     *
     * @var string
     * @access protected
     */
    protected $_sameSite;

    /**
     * \Mvc\Http\Cookie constructor
     *
     * @param string $name
     * @param string $value
     * @param int|null $expire
     * @param string|null $path
     * @param boolean|null $secure
     * @param string|null $domain
     * @param boolean|null $httpOnly
     * @param string|null $sameSite
     * @throws Exception
     */
    public function __construct($name, $value = null, $expire = null, $path = null, $secure = null, $domain = null, $httpOnly = null, $sameSite = null)
    {
        /* Type check */
        if (is_string($name) === false) {
            throw new Exception('The cookie name must be string');
        }

        $this->_value = $value;

        if (is_null($expire) === true) {
            $expire = 0;
        } elseif (is_int($expire) === false) {
            throw new Exception('Invalid parameter type.');
        }

        if (is_null($path) === true) {
            $path = '/';
        } elseif (is_string($path) === false) {
            throw new Exception('Invalid parameter type.');
        }

        if (is_bool($secure) === true) {
            $this->_secure = $secure;
        }

        if (is_string($domain) === true) {
            $this->_domain = $domain;
        }

        if (is_bool($httpOnly) === true) {
            $this->_httpOnly = $httpOnly;
        }

        if (is_string($sameSite) === true) {
            $this->_sameSite = $sameSite;
        }

        /* Update property */
        $this->_name   = $name;
        $this->_expire = $expire;
    }

    /**
     * Sets the cookie's value
     *
     * @param string $value
     * @return \Mvc\Http\CookieInterface
     * @throws Exception
     */
    public function setValue($value)
    {
        if (is_string($value) === false) {
            throw new Exception('Invalid parameter type.');
        }

        $this->_value  = $value;
        $this->_readed = true;
    }

    /**
     * Returns the cookie's value
     *
     * @param string|array|null $filters
     * @param string|null $defaultValue
     * @return mixed
     * @throws Exception
     */
    public function getValue($filters = null, $defaultValue = null)
    {
        if (is_null($filters) === false &&
            is_string($filters) === false &&
            is_array($filters) === false) {
            throw new Exception('Invalid parameter type.');
        }

        if (is_string($defaultValue) === false &&
            is_null($defaultValue) === false) {
            throw new Exception('Invalid parameter type.');
        }

        if ($this->_restored === false) {
            $this->restore();
        }

        if ($this->_readed === false) {
            $name = $this->_name;

            if (isset($_COOKIE[$name]) === true) {
                $value = $_COOKIE[$name];
                if ($this->_useEncryption === true) {
                    $crypt = App::$di->get('crypt');
                    if ($crypt instanceof CryptInterface === false) {
                        throw new Exception('Wrong crypt service.');
                    }

                    //Decrypt the value also decoding it with base64
                    $value = $crypt->decryptBase64($value);
                }

                //Update the value
                $this->_value = $value;

                if (is_null($filters) === false) {
                    $filter = $this->_filter;
                    if (is_object($filter) === false) {
                        $filter = App::$di->get('filter');
                        if ($filter instanceof FilterInterface === false) {
                            throw new Exception('Wrong filter service.');
                        }

                        $this->_filter = $filter;
                    }

                    return $filter->sanitize($value, $filters);
                }

                //Return the value without filtering
                return $value;
            }

            return $defaultValue;
        }

        return $this->_value;
    }

    /**
     * Sends the cookie to the HTTP client
     * Stores the cookie definition in session
     *
     * @return \Mvc\Http\Cookie
     * @throws Exception
     */
    public function send()
    {
        //@note no interface validation
        if (App::$di->has('session')) {
            $definition = array();
            if ($this->_expire !== 0) {
                $definition['expire'] = $this->_expire;
            }

            if (empty($this->_path) === false) {
                $definition['path'] = $this->_path;
            }

            if (empty($this->_domain) === false) {
                $definition['domain'] = $this->_domain;
            }

            if (empty($this->_secure) === false) {
                $definition['secure'] = $this->_secure;
            }

            if (empty($this->_httpOnly) === false) {
                $definition['httpOnly'] = $this->_httpOnly;
            }

            if (empty($this->_sameSite) === false) {
                $definition['sameSite'] = $this->_sameSite;
            }

            //The definition is stored in session
            if (count($definition) !== 0) {
                $session = App::$di->get('session');
                if (is_null($session) === false) {
                    if ($session instanceof SessionInterface === false) {
                        throw new Exception('Wrong session service.');
                    }

                    $session->set('_PHCOOKIE_' . $this->_name, $definition);
                }
            }
        }

        /* Encryption */
        if ($this->_useEncryption === true && empty($this->_value) === false) {
            $crypt = App::$di->get('crypt');
            if ($crypt instanceof CryptInterface === false) {
                throw new Exception('Wrong crypt service.');
            }

            //Encrypt the value also coding it with base64
            $value = $crypt->encryptBase64($this->_value);
        } else {
            $value = $this->_value;
        }

        $path = $this->_path;

        setcookie((string) $this->_name, (string) $value, [
            'expires'  => (int) $this->_expire,
            'path'     => (string) $path,
            'domain'   => (string) $this->_domain,
            'secure'   => (bool) $this->_secure,
            'httponly' => (bool) $this->_httpOnly,
            'samesite' => (string) $this->_sameSite,
        ]);

        return $this;
    }

    /**
     * Reads the cookie-related info from the SESSION to restore the cookie as it was set
     * This method is automatically called internally so normally you don't need to call it
     *
     * @return \Mvc\Http\Cookie
     */
    public function restore()
    {
        if ($this->_restored === false) {
            //@note no interface check
            if (App::$di->has('session')) {
                $session = App::$di->get('session');

                if ($session instanceof SessionInterface === false) {
                    throw new Exception('Wrong session sevice.');
                }

                //@note no kind of session data validation

                $definition = $session->get('_PHCOOKIE_' . $this->_name);
                if (is_array($definition) === true) {
                    /* Read definition */
                    if (isset($definition['expire']) === true) {
                        $this->_expire = $definition['expire'];
                    }

                    if (isset($definition['domain']) === true) {
                        $this->_domain = $definition['domain'];
                    }

                    if (isset($definition['path']) === true) {
                        $this->_path = $definition['path'];
                    }

                    if (isset($definition['secure']) === true) {
                        $this->_secure = $definition['secure'];
                    }

                    if (isset($definition['httpOnly']) === true) {
                        $this->_httpOnly = $definition['httpOnly'];
                    }

                    if (isset($definition['sameSite']) === true) {
                        $this->_sameSite = $definition['sameSite'];
                    }
                }
            }

            $this->_restored = true;
        }

        return $this;
    }

    /**
     * Deletes the cookie by setting an expire time in the past
     *
     * @throws Exception
     */
    public function delete()
    {
        if (App::$di->has('session')) {
            $session = App::$di->get('session');

            if ($session instanceof SessionInterface === false) {
                throw new Exception('Wrong session service.');
            }

            $session->remove('_PHCOOKIE_' . $this->_name);
        }

        $this->_value = null;

        $path = $this->_path;
        if ($this->_sameSite) {
            $path .= ';samesite=' . $this->_sameSite;
        }

        //@note use the type 'boolean' for the last two parameters
        setcookie(
            (string) $this->_name,
            null,
            time() - 691200,
            (string) $path,
            (string) $this->_domain,
            (bool) $this->_secure,
            (bool) $this->_httpOnly
        );
    }

    /**
     * Sets if the cookie must be encrypted/decrypted automatically
     *
     * @param boolean $useEncryption
     * @return \Mvc\Http\Cookie
     * @throws Exception
     */
    public function useEncryption($useEncryption)
    {
        if (is_bool($useEncryption) === false) {
            throw new Exception('Invalid parameter type.');
        }

        $this->_useEncryption = $useEncryption;

        return $this;
    }

    /**
     * Check if the cookie is using implicit encryption
     *
     * @return boolean
     */
    public function isUsingEncryption()
    {
        return $this->_useEncryption;
    }

    /**
     * Sets the cookie's expiration time
     *
     * @param int $expire
     * @return \Mvc\Http\Cookie
     * @throws Exception
     */
    public function setExpiration($expire)
    {
        if (is_int($expire) === false) {
            throw new Exception('Invalid parameter type.');
        }

        if ($this->_restored === false) {
            $this->restore();
        }

        $this->_expire = $expire;

        return $this;
    }

    /**
     * Returns the current expiration time
     *
     * @return string
     */
    public function getExpiration()
    {
        if ($this->_restored === false) {
            $this->restore();
        }

        return (string) $this->_expire;
    }

    /**
     * Sets the cookie's expiration time
     *
     * @param string $path
     * @return \Mvc\Http\Cookie
     * @throws Exception
     */
    public function setPath($path)
    {
        if (is_string($path) === false) {
            throw new Exception('Invalid parameter type.');
        }

        if ($this->_restored === false) {
            $this->restore();
        }

        $this->_path = $path;

        return $this;
    }

    /**
     * Returns the current cookie's path
     *
     * @return string
     */
    public function getPath()
    {
        if ($this->_restored === false) {
            $this->restore();
        }

        return (string) $this->_path;
    }

    /**
     * Sets the domain that the cookie is available to
     *
     * @param string $domain
     * @return \Mvc\Http\Cookie
     * @throws Exception
     */
    public function setDomain($domain)
    {
        if (is_string($domain) === false) {
            throw new Exception('Invalid parameter type.');
        }

        if ($this->_restored === false) {
            $this->restore();
        }

        $this->_domain = $domain;

        return $this;
    }

    /**
     * Returns the domain that the cookie is available to
     *
     * @return string
     */
    public function getDomain()
    {
        if ($this->_restored === false) {
            $this->restore();
        }

        return (string) $this->_domain;
    }

    /**
     * Sets if the cookie must only be sent when the connection is secure (HTTPS)
     *
     * @param boolean $secure
     * @return \Mvc\Http\Cookie
     * @throws Exception
     */
    public function setSecure($secure)
    {
        if (is_bool($secure) === false) {
            throw new Exception('Invalid parameter type.');
        }

        if ($this->_restored === false) {
            $this->restore();
        }

        $this->_secure = $secure;
    }

    /**
     * Returns whether the cookie must only be sent when the connection is secure (HTTPS)
     *
     * @return boolean
     */
    public function getSecure()
    {
        if ($this->_restored === false) {
            $this->restore();
        }

        return $this->_secure;
    }

    /**
     * Sets if the cookie is accessible only through the HTTP protocol
     *
     * @param boolean $httpOnly
     * @return \Mvc\Http\Cookie
     * @throws Exception
     */
    public function setHttpOnly($httpOnly)
    {
        if (is_bool($httpOnly) === false) {
            throw new Exception('Invalid parameter type.');
        }

        if ($this->_restored === false) {
            $this->restore();
        }

        $this->_httpOnly = $httpOnly;

        return $this;
    }

    /**
     * Returns if the cookie is accessible only through the HTTP protocol
     *
     * @return boolean
     */
    public function getHttpOnly()
    {
        if ($this->_restored === false) {
            $this->restore();
        }

        return $this->_httpOnly;
    }

    /**
     * setSameSite
     *
     * @param string $sameSite
     * @return \Mvc\Http\Cookie
     * @throws Exception
     */
    public function setSameSite($sameSite)
    {
        if (is_string($sameSite) === false) {
            throw new Exception('Invalid parameter type.');
        }

        if ($this->_restored === false) {
            $this->restore();
        }

        $this->_sameSite = $sameSite;

        return $this;
    }

    /**
     * getSameSite
     *
     * @return string
     */
    public function getSameSite()
    {
        if ($this->_restored === false) {
            $this->restore();
        }

        return $this->_sameSite;
    }

    /**
     * Magic __toString method converts the cookie's value to string
     *
     * @return mixed
     */
    public function __toString()
    {
        if (is_null($this->_value) === true) {
            try {
                return (string) $this->getValue();
            } catch (\Exception $e) {
                trigger_error((string) $e->getMessage(), \E_USER_ERROR);
            }
        }

        return (string) $this->_value;
    }
}
