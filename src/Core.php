<?php
namespace trident;

if (!defined('DISPLAY_ERRORS')) {
    define('DISPLAY_ERRORS', false);
}
if (!defined('DEBUG')) {
    define('DEBUG', true);
}
if (!defined('PROFILE')) {
    define('PROFILE', false);
}
if (!defined('CORE_ENABLE_EXCEPTION_HANDLER')) {
    define('CORE_ENABLE_EXCEPTION_HANDLER', false);
}
if (!defined('CORE_ENABLE_ERROR_HANDLER')) {
    define('CORE_ENABLE_ERROR_HANDLER', false);
}

class Core
{
    // Release version and codename
    const VERSION = '1.0.1';
    const CODENAME = 'Rapid';

    // Common environment type constants for consistency and convenience
    const PRODUCTION = 10;
    const STAGING = 20;
    const TESTING = 30;
    const DEVELOPMENT = 40;

    const ENCODING = 'utf-8';

    protected static $appComponents = [];
    protected static $defaultAppComponent = 'system';
    protected static $defaultAction = 'index';

    protected static $inputPointToHide = 'index';
    protected static $hideInputPoint = false;

    protected static $expose = true;

    protected static $context;

    protected static $runtimePath;
    protected static $runtimeAppComponentsDir;
    protected static $initiated = false;

    public static function init(array $properties, $classes = array())
    {
        if (self::$initiated === true) {
            return;
        }

        if (!empty($properties)) {
            foreach ($properties as $k => $v) {
                self::$$k = $v;
            }
        }

        if (!function_exists('mb_substr')) {
            throw new \RuntimeException('');
        }
        /**
         * If string overloading is active, it will break many of the
         * native implementations. mbstring.func_overload must be set
         * to 0, 1 or 4 in php.ini (string overloading disabled).
         * Also need to check we have the correct internal mbstring
         * encoding
         */
        if (extension_loaded('mbstring')) {
            if (ini_get('mbstring.func_overload')) {
                throw new \RuntimeException(
                    'String functions are overloaded by mbstring. "mbstring.func_overload" must be set to 0 in php.ini'
                );
            }
            if (true === function_exists('mb_internal_encoding')) {
                mb_internal_encoding('UTF-8');
            }
            if (true === function_exists('mb_regex_encoding')) {
                mb_regex_encoding('UTF-8');
            }
            /**
             * Set the mb_substitute_character to "none"
             *
             * @link http://www.php.net/manual/function.mb-substitute-character.php
             */
            mb_substitute_character('none');
        }
        /**
         * Check whether PCRE has been compiled with UTF-8 support
         */
        $UTF8_ar = array();
        if (preg_match('/^.{1}$/u', "ñ", $UTF8_ar) != 1) {
            trigger_error('PCRE is not compiled with UTF-8 support', E_USER_ERROR);
        }
        unset($UTF8_ar);

        foreach ($classes as $className => $classConfig) {
            if (method_exists($className, 'init')) {
                $className::init($classConfig);
            }
        }
        self::$initiated = true;

    }

    public static function expose()
    {
        return (bool)self::$expose;
    }

    public static function hideInputPoint()
    {
        return (bool)self::$hideInputPoint;
    }

    public static function inputPointToHide()
    {
        return self::$inputPointToHide;
    }

    public static function poweredBy()
    {
        return 'Trident Framework '.self::VERSION.' ('.self::CODENAME.' Trident)';
    }

    public static function getAppComponents()
    {
        return self::$appComponents;
    }

    public static function getDefaultAppComponent()
    {
        return self::$defaultAppComponent;
    }

    public static function getDefaultAction()
    {
        return self::$defaultAction;
    }

    /**
     * @param $route
     * @param null $request
     * @param null $response
     * @return object trident\Triad
     */
    public static function getAppComponentFromRoute($route = null, $request = null, $response = null)
    {
        $component = self::getComponentConfigFromRoute($route);
        $argument = &$component['argument'];
        $argument['request'] = $request;
        $argument['response'] = $response;
        $component['className'] = empty($component['className']) ? 'trident\\Triad' : $component['className'];
        $component['instanceof'] = 'trident\\Triad';

        return DI::build($component, true, true);
    }

    public static function getComponentConfigFromRoute($route = null)
    {
        if (empty($route)) {
            $route = self::$defaultAppComponent;
        }

        $route = trim($route, '/');
        if (strpos($route, '/') === false) {
            if (empty(self::$appComponents[$route])) {
                throw new \RuntimeException('not found component "'.$route.'"');
            }
            $config = self::$appComponents[$route];

            $config['argument'] = isset($config['argument']) ? $config['argument'] : [];
            $config['argument']['route'] = $route;
            $config['argument']['id'] = $route;

            return $config;
        }
        $ids = explode('/', $route);

        $config = self::$appComponents;
        $componentId = null;
        $route = [];
        foreach ($ids as $id) {
            if (isset($config[$id])) {
                $config = $config[$id];
                $componentId = $route[] = $id;
                if (isset($config['components'])) {
                    $config = $config['components'];
                    continue;
                }
            }
            break;
        }
        $route = implode('/', $route);

        $config['argument'] = isset($config['argument']) ? $config['argument'] : [];
        $config['argument']['route'] = $route;
        $config['argument']['id'] = $componentId;

        return $config;
    }
}

?>
