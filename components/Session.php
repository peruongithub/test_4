<?php
namespace components;


use trident\Object;
use trident\Request;

class Session extends Object
{
    const SESSION_NAME = 'session_id';
    /**
     * @var  integer  Number of seconds before the cookie expires
     */
    protected $cookieExpire = 0;
    /**
     * @var $request Request
     */
    protected $request;

    public function __construct(array $options = [])
    {
        parent::__construct($options);

        $request = Request::initial();

        if (!$request instanceof Request) {
            throw new \RuntimeException('You can not use Session before running Request.');
        }
        $this->request = $request;

        $id = $request->getCookie(self::SESSION_NAME, false);

        if ($id) {
            $this->start($id);
        }
    }

    public function start($id = null)
    {

        if (PHP_SESSION_NONE === \session_status()) {
            if ($id) {
                // Set the session id
                \session_id($id);
            }

            \session_name(self::SESSION_NAME);
            // Start the session
            \session_start();

            $response = $this->request->getResponse();

            $response->setCookie(self::SESSION_NAME, \session_id(), $this->cookieExpire);
        }

    }

    public function destroy()
    {
        $id = \session_id();

        \session_destroy();
        //session_regenerate_id(true);

        $response = $this->request->getResponse();

        $response->deleteCookie(self::SESSION_NAME);

        return $id;
    }

    /**
     * Get a variable from the session array.
     *
     *     $foo = $session->get('foo');
     *
     * @param   string $key variable name
     * @param   mixed $default default value to return
     * @return  mixed
     */
    public function get($key, $default = null)
    {
        return !empty($_SESSION) && array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
    }

    /**
     * Set a variable in the session array.
     *
     *     $session->set('foo', 'bar');
     *
     * @param   string $key variable name
     * @param   mixed $value value
     * @return  $this
     */
    public function set($key, $value)
    {
        $_SESSION[$key] = $value;

        return $this;
    }

    public function delete($key)
    {
        unset($_SESSION[$key]);

        return $this;
    }
}