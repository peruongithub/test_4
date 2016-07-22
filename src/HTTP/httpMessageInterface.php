<?php
namespace trident\HTTP;

interface httpMessageInterface
{

    public function body($content = null);

    public function getBody();

    public function getSpecialHeaderString();

    public function setHeaders($name, $value = null);

    public function addCacheControlDirective($key, $value = true);

    public function hasCacheControlDirective($key);

    public function getCacheControlDirective($key);

    public function removeCacheControlDirective($key);

    public function getHeaders($name = null);

    public function setCookie(
        $name,
        $value = null,
        $expire = 0,
        $path = '/',
        $domain = null,
        $secure = false,
        $httpOnly = true
    );

    public function setCookieFromArray(array $cookies);

    public function setCookieFromString($cookie);

    public function getCookie($name, $default = null);

    public function deleteCookie($name);
} 