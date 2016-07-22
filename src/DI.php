<?php
namespace trident;

class DI
{
    /**
     * service marker
     */
    const SM = '$::';
    /**
     * object marker
     */
    const OM = '@::';
    /**
     * service marker length
     */
    const SML = 3;
    /**
     * object marker length
     */
    const OML = 3;
    private static $services = [

    ];


    public static function init(array $services = [])
    {
        if (!empty($services) && is_array($services)) {
            foreach ($services as $name => $config) {
                self::set($name, $config);
            }

            //self::$services = $services;
        }
    }

    /**
     * $array(
     *         name -> (string)
     *         alias -> (string)
     *
     *         className ->(string)
     *         classPath ->(string)
     *
     *         arguments -> array(argument, service_name => params_array, argument)
     *
     *         calls -> array(method => array(argument, service_name => params_array, argument))
     *
     *         properties -> array(name => value)
     *         services -> array(service_name => params)
     *
     *         events -> array(eventName => func)
     *
     *      )
     *
     */
    public static function set($name, $service)
    {
        if ($name === null) {
            return false;
        }
        if (isset(self::$services[$name])) {
            return true;
        }
        if (is_callable($service)) {
            $service = call_user_func($service);
        }
        if (is_object($service)) {
            self::$services[$name]['public'] = true;
            self::$services[$name]['obj'] = $service;
        } elseif (is_string($service) && strpos($service, ',') !== false) {
            self::$services[$name] = $service;
        } elseif (is_array($service) && false === self::setFromArray($name, $service)) {
            return false;
        }

        return true;
    }

    private static function setFromArray($name, array $service)
    {
        $className = !empty($service['className']) ? $service['className'] : null;

        if ($className === null) {
            return false;
        }


        $setServices = function (&$service) {
            foreach ($service as $key => $val) {
                if (self::is_service_str($key)) {
                    if (self::set(($keyS = substr($key, self::SML)), $val) === true) {
                        $service[$key] = $keyS;
                        continue;
                    } else {
                        return false;
                    }
                }
            }

            return true;
        };

        if (
            !empty($service['argument']) &&
            self::is_service_str($service['argument']) &&
            !isset(self::$services[substr($service['argument'], self::SML)])
        ) {
            return false;
        }

        $as = array('properties', 'arguments');

        foreach ($as as $key) {
            if (empty($service[$key])) {
                continue;
            } elseif ($setServices($service[$key]) === false) {
                return false;
            }
        }
        if (!empty($service['calls'])) {
            foreach ($service['calls'] as $method => $args) {
                if ($setServices($service['calls'][$method]) === false) {
                    return false;
                }
            }
        }

        $service['public'] = !empty($service['public']) ? $service['public'] : true;
        //save
        self::$services[$name] = $service;
        self::$services[$name]['obj'] = null;

        /*array(
            'name' => $name,
            'public' => $public,
            'className' => $className,
            'classPath' => $classPath,
            'constructor' => $constructor,
            'arguments' => $arguments,
            'properties' => $properties,
            'shutdownMethod' => $shutdownMethod,
            'restoreMethod' => $restoreMethod,
            'calls' => $calls,
            'events' => $events,
            'collection' => array(),
            'obj' => null
        );*/

        return true;
    }

    public static function is_service_str($string)
    {
        return is_string($string) && strncmp($string, self::SM, self::SML) === 0;
    }

    public static function has($name)
    {
        return isset(self::$services[$name]);
    }

    public static function get($name)
    {
        if ($name === null || !isset(self::$services[$name])) {
            return false;
        }
        if (!empty(self::$services[$name]['obj'])) {
            return self::$services[$name]['obj'];
        }

        return self::getInternal($name, false);
    }

    private static function getInternal($name, $internal = true, $level = 0)
    {
        if ($level >= 9) {
            return false;
        }

        if (is_string(self::$services[$name])) {
            $services = explode(',', self::$services[$name]);
            foreach ($services as $service) {
                $service = trim($service);
                $service = self::getInternal($service, false, $level++);
                if ($service !== false) {
                    self::$services[$name]['public'] = true;

                    return self::$services[$name]['obj'] = $service;
                }
            }
        }
        $service = &self::$services[$name];
        if ((!isset($service['public']) || $service['public'] === true) || $internal === true) {
            if (isset($service['obj']) && is_object($service['obj'])) {
                return $service['obj'];
            } else {
                $service['name'] = $name;

                return self::buildServiceObj($service, true, true);
            }
        } else {
            return false;
        }
    }

    private static function buildServiceObj($service, $save = true, $loadConfig = true, $deep = false)
    {
        $service = self::loadClassEndConfig($service, $loadConfig, $deep);
        $name = isset($service['name']) ? $service['name'] : false;

        if (!is_object($service['className'])) {
            if (
                isset($service['constructor']) &&
                $service['constructor'] !== '__construct' &&
                method_exists($service['className'], $service['constructor'])
            ) {
                $callable = [$service['className'], $service['constructor']];
            } else {
                $callable = [new \ReflectionClass($service['className']), 'newInstance'];
            }

            if (!empty($service['arguments'])) {
                $serviceObj =
                    call_user_func_array($callable, self::prepareArguments($service['arguments'], false));
            } elseif (!empty($service['argument'])) {
                if (is_string($service['argument']) && self::is_service_str($service['argument'])) {
                    $service['argument'] = self::getInternal(substr($service['argument'], self::SML), true);
                } elseif (is_array($service['argument'])) {
                    $service['argument'] = self::prepareArguments($service['argument'], true);
                }
                $serviceObj = call_user_func($callable, $service['argument']);
            } else {
                $serviceObj = call_user_func_array($callable, []);
            }

        } else {
            $serviceObj = $service['className'];
        }

        $name = $name ? $name : get_class($serviceObj);

        if (!empty($service['properties'])) {
            $properties = self::prepareArguments($service['properties'], true);
            foreach ($properties as $key => $value) {
                $serviceObj->$key = $value;
            }
        }

        if (!empty($service['calls'])) {
            $serviceObj = self::chained($serviceObj, $service['calls']);
        }

        if (!empty($service['instaceof']) && !$serviceObj instanceof $service['instaceof']) {
            throw new \RuntimeException(
                'Сконструированный объект не соответствует требуемому: "'.$service['instaceof'].'"'
            );
        }

        if ($save === true && !empty($name)) {
            self::$services[$name]['obj'] = $serviceObj;
        }

        return $serviceObj;
    }

    private static function loadClassEndConfig($service, $loadConfig = true, $deep = false)
    {
        /*
        if (is_object($service['className'])) {
            $config = $loadConfig ? AL::getConf(get_class($service['className']),$deep) : [];
        } elseif (empty($service['classPath']) && AL::load($service['className'])) {
            $config = $loadConfig ? AL::getConf($service['className'],$deep) : [];
        } elseif (!empty($service['classPath']) && AL::loadFile($service['classPath'], $service['className'])) {
            $config = $loadConfig ? AL::getConf($service['className'],$deep) : [];
        } else {
            throw new \InvalidArgumentException(
                'Can not load Class "' . $service['className'] . '" by path "' . $service['classPath'] . '"'
            );
        }
        return \array_replace_recursive($config, $service);
        */
        return $service;
    }

    public static function prepareArguments(array $arguments, $assoc = false)
    {
        $a = array();

        foreach ($arguments as $key => $value) {
            if (is_string($key) && self::is_service_str($key)) {
                $o = self::getInternal($value, true);
                if ($o !== false) {
                    $a[substr($key, self::SML)] = $o;
                }
            } elseif (is_string($key) && self::is_object_str($key)) {
                $o = self::build($value, true);
                if ($o !== false) {
                    $a[substr($key, self::OML)] = $o;
                }
            } else {
                $a[$key] = $value;
            }
        }

        return $assoc ? $a : array_values($a);
    }

    public static function is_object_str($string)
    {
        return strncmp($string, self::OM, self::OML) === 0;
    }

    /**
     * * $conf = $array(
     *         className ->(string)
     *         classPath ->(string)
     *
     *         arguments -> array(argument, service_name => params_array, argument)
     *         calls -> array(method => array(argument, service_name => params_array, argument))
     *         properties -> array(name => value)
     *         services -> array(prop_name => params_array)
     *         events -> array(eventName => func)
     *      )
     * @param mixed $className
     * @param bool $loadConfig
     * @return mixed
     */
    public static function build($className, $loadConfig = false, $deep = false)
    {
        if (is_string($className)) {
            $conf = array('className' => $className, 'name' => $className);
        } elseif (is_array($className)) {
            $conf = $className;
        } else {
            return false;
        }

        if (empty($conf['className'])) {
            return false;
        }

        return self::buildServiceObj($conf, false, $loadConfig, $deep);
    }

    private static function chained($serviceObj, $chain)
    {
        foreach ($chain as $method => $args) {
            if ($method == 'chain') {
                $serviceObj = self::chained($serviceObj, $args);
            } else {
                $args = self::prepareArguments($args);
                call_user_func_array(array($serviceObj, $method), $args);
            }
        }

        return $serviceObj;
    }

    public static function getNew($name, array $service = [])
    {
        if (
            $name === null ||
            !isset(self::$services[$name]) ||
            (isset(self::$services[$name]['public']) && self::$services[$name]['public'] === false) ||
            (isset(self::$services[$name]['instance']) && self::$services[$name]['instance'] === true)
        ) {
            return false;
        }
        unset($service['obj']);
        $service = array_replace_recursive(self::$services[$name], $service);
        $service['name'] = $name;

        return self::buildServiceObj($service, false, true);
    }

    public static function getByRange(array $rangeList = [])
    {
        $service = false;
        foreach ($rangeList as $service) {
            $service = self::getInternal($service, false, 0);
            if ($service !== false) {
                break;
            }
        }

        return $service;
    }


}

?>
