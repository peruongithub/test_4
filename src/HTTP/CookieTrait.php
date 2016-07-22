<?php
namespace trident\HTTP;

use trident\Server;
use trident\Request;
use trident\Response;

trait CookieTrait
{
    use LockTrait;
    protected $userAgent;
    /**
     * @var  string  Magic salt to add to the cookie
     */
    protected $cookieSalt = '88b184adea10bf987b15257a5d6c5cb94eba69d3';

    /**
     * @var  integer  Number of seconds before the cookie expires
     */
    protected $cookieExpire = 0;

    /**
     * @var  string  Restrict the path that the cookie is available to
     */
    protected $cookiePath = '/';

    /**
     * @var  string  Restrict the domain that the cookie is available to
     */
    protected $cookieDomain;

    /**
     * @var  boolean  Only transmit cookies over secure connections
     */
    protected $cookieSecure = false;

    /**
     * @var  boolean  Only transmit cookies over HTTP, disabling Javascript access
     */
    protected $cookieHttpOnly = true;

    protected $cookies = [];

    protected $cookieHeaderKey;

    /**
     * Gets the value of a signed cookie. Cookies without signatures will not
     * be returned. If the cookie signature is present, but invalid, the cookie
     * will be deleted.
     *
     * @param   string $name cookie name
     * @param   mixed $default default value to return
     * @return  string
     */
    public function getCookie($name, $default = null)
    {
        if (empty($this->cookies[$name])) {
            // The cookie does not exist
            return $default;
        }

        if (isset($this->cookies[$name][40]) AND $this->cookies[$name][40] === '~') {
            // Separate the salt and the value
            list ($hash, $value) = explode('~', $this->cookies[$name], 2);

            if ($this->salt($name, $value) === $hash) {
                // Cookie signature is valid
                return $value;
            }
        }
        // The cookie signature is invalid, delete it
        $this->deleteCookie($name);

        return $default;
    }

    /**
     * Generates a salt string for a cookie based on the name and value.
     * @param   string $name name of cookie
     * @param   string $value value of cookie
     * @return  string
     */
    protected function salt($name, $value)
    {
        // Require a valid salt
        if (!$this->cookieSalt) {
            throw new \InvalidArgumentException(
                'A valid cookie salt is required. Please set Cookie::$salt in your bootstrap.php. For more information check the documentation'
            );
        }

        return sha1($name.$this->cookieSalt.$value);//$this->userAgent.
    }

    /**
     * Deletes a cookie by making the value NULL and expiring it.
     * @param   string $name cookie name
     * @return  void
     */
    public function deleteCookie($name)
    {
        $this->cookies[$name] = [
            'value' => 'deleted',
            'domain' => isset($this->cookies[$name]['domain']) ? $this->cookies[$name]['domain'] : $this->cookieDomain,
            'expire' => time() - 31536001,
            'path' => isset($this->cookies[$name]['path']) ? $this->cookies[$name]['path'] : $this->cookiePath,
            'secure' => isset($this->cookies[$name]['secure']) ? $this->cookies[$name]['secure'] : $this->cookieSecure,
            'httpOnly' => isset($this->cookies[$name]['httpOnly']) ? $this->cookies[$name]['httpOnly'] : $this->cookieHttpOnly,
        ];
    }

    public function getCookies()
    {
        return empty($this->cookies) ? null : $this->cookies;
    }

    /**
     * Returns the cookie as a string.
     *
     * @return string The cookie
     */
    public function cookieToString()
    {
        if (!empty($this->cookies)) {
            return $this->cookieHeaderKey.':'.$this->strCookie();
        }

        return '';
    }

    protected function strCookie()
    {
        if (!empty($this->cookies)) {
            $cookieStrings = [];
            if ('Set-Cookie' == $this->cookieHeaderKey) {
                foreach ($this->cookies as $name => $cookie) {
                    $str = urlencode($name).'='.urlencode($cookie['value']);
                    if ($cookie['expire'] !== 0) {
                        $str .= '; expires='.gmdate("D, d-M-Y H:i:s T", $cookie['expire']);
                    }

                    if ('/' !== $this->cookiePath) {
                        $str .= '; path='.$cookie['path'];
                    }

                    $str .= '; domain='.$cookie['domain'];

                    if (true === $cookie['secure']) {
                        $str .= '; secure';
                    }

                    if (true === $cookie['httpOnly']) {
                        $str .= '; httponly';
                    }

                    $cookieStrings[] = $str;
                }

                return implode("\r\nSet-Cookie:", $cookieStrings)."\r\n";
            } else {
                foreach ($this->cookies as $name => $cookie) {
                    $cookieStrings[] = urlencode($name).'='.urlencode($cookie['value']);
                }

                return implode(';', $cookieStrings)."\r\n";
            }
        }

        return '';
    }

    public function sendCookie()
    {

        /*if('Set-Cookie' == $this->cookieHeaderKey &&!empty($this->cookies)){
            foreach($this->cookies as $name => $cookie){
                setcookie($name,$cookie['value'],$cookie['expire'],
                    $cookie['path'],$cookie['domain'],$cookie['secure'],$cookie['httpOnly']);
            }
        }*/
    }

    protected function initCookieTrait()
    {
        $this->userAgent = $this->userAgent == null ? Server::getUserAgent() : $this->userAgent;
        $this->cookieDomain = $this->cookieDomain == null ? Server::getHost() : $this->cookieDomain;
        $this->cookieSecure = (Boolean)$this->cookieSecure;
        $this->cookieHttpOnly = (Boolean)$this->cookieHttpOnly;

        $is_empty = empty($this->cookies);
        if (!$is_empty && is_array($this->cookies)) {
            $this->setCookieFromArray($this->cookies);
        } elseif (!$is_empty && is_string($this->cookies)) {
            $this->setCookieFromString($this->cookies);
        }
        if ($this instanceof Request) {
            $this->cookieHeaderKey = 'Cookie';
        } elseif ($this instanceof Response) {
            $this->cookieHeaderKey = 'Set-Cookie';
        }

    }

    public function setCookieFromArray(array $cookies)
    {
        foreach ($cookies as $name => $value) {
            if (is_array($value)) {
                continue;
            } elseif ($this->cookieHeaderKey == 'Cookie') {
                if (isset($value[40]) && $value[40] === '~') {
                    $this->cookies[$name] = ['value' => $value];
                }
            } elseif ($this->cookieHeaderKey == 'Set-Cookie') {
                $this->setCookie($name, $value);
            }
        }
    }

    /**
     * @param string $name The name of the cookie
     * @param string $value The value of the cookie
     * @param integer|string|\DateTime $expire The time the cookie expires
     * @param string $path The path on the server in which the cookie will be available on
     * @param string $domain The domain that the cookie is available to
     * @param Boolean $secure Whether the cookie should only be transmitted over a secure HTTPS connection from the client
     * @param Boolean $httpOnly Whether the cookie will be made accessible only through the HTTP protocol
     *
     * @throws \InvalidArgumentException
     */
    public function setCookie(
        $name,
        $value = null,
        $expire = 0,
        $path = '/',
        $domain = null,
        $secure = false,
        $httpOnly = true
    ) {
        if (empty($name)) {
            throw new \InvalidArgumentException('The cookie name cannot be empty.');
        }
        // from PHP source code
        if (preg_match("/[=,; \t\r\n\013\014]/", $name)) {
            throw new \InvalidArgumentException(sprintf('The cookie name "%s" contains invalid characters.', $name));
        }
        // convert expiration time to a Unix timestamp
        if ($expire instanceof \DateTime) {
            $expire = $expire->format('U');
        } elseif (!is_numeric($expire)) {
            $expire = strtotime($expire);

            if (false === $expire || -1 === $expire) {
                throw new \InvalidArgumentException('The cookie expiration time is not valid.');
            }
        }
        $this->cookies[$name] = [
            'value' => $this->salt($name, $value).'~'.$value,
            'domain' => $domain == null ? $this->cookieDomain : $domain,
            'expire' => ($expire == null ? $this->cookieExpire : $expire),
            'path' => empty($path) ? $this->cookiePath : $path,
            'secure' => (Boolean)$secure,
            'httpOnly' => (Boolean)$httpOnly,
        ];
    }

    public function setCookieFromString($cookie)
    {
        if ($this->cookieHeaderKey == 'Set-Cookie') {
            if (($pos = strpos('path=', $cookie)) !== false) {
                $cookie = substr($cookie, 0, $pos);
            } else {
                if (($pos = strpos('domain=', $cookie)) !== false) {
                    $cookie = substr($cookie, 0, $pos);
                }
            }
        }
        $cp = explode(';', $cookie);
        foreach ($cp as $string) {
            if (strpos('=', $cookie) !== false) {
                list($name, $value) = explode('=', $string);
                $this->setCookie($name, $value);
            }
        }
    }

}
