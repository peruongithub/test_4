<?php
namespace trident;

class Component extends Object
{

    protected $_extends = array(); // objects
    protected $_extendsM = array(); // methods
    protected $_extendsV = array(); // vars

    public function __construct(array $options = [])
    {
        if (isset($options['mixClasses'])) {
            $this->extend($options['mixClasses']);
            unset($options['mixClasses']);
        }

        parent::__construct($options);
    }

    protected function extend($Classes)
    {
        foreach ($Classes as $ClassSet) {
            if (empty($ClassSet['className'])) {
                continue;
            }

            $key = substr(md5($ClassSet['className']), 5);

            if (isset($this->_extends[$key])) {
                continue;
            }
            if (!isset($ClassSet['properties'])) {
                $ClassSet['properties'] = array();
            }
            $ClassSet['properties']['_parent'] = $this;
            $classObj = DI::build($ClassSet);

            if ($classObj === false) {
                continue;
            } else {
                $this->_extends[$key] = $classObj;
                $class_methods = get_class_methods($classObj);
                $class_vars = array_keys(get_object_vars($classObj));
                foreach ($class_methods as $method) {
                    $this->_extendsM[$method] = $key;
                }
                foreach ($class_vars as $var => $value) {
                    $this->_extendsV[$var] = $key;
                }
            }
        }
    }

    public function getDependencyConfig()
    {
        return array(
            'properties' => array(),
            'calls' => array(),
            'events' => array(),
        );
    }

    public function getObject($method)
    {
        return method_exists(
            $this,
            $method
        ) ? $this : (isset($this->_extendsM[$method]) ? $this->_extends[$this->_extendsM[$method]] : false);
    }

    public function __call($method, $args)
    {
        if (isset($this->_extendsM[$method])) {
            return call_user_func_array(array($this->_extends[$this->_extendsM[$method]], $method), $args);
        } else {
            if (class_exists('\Closure', false)) {
                if (isset($this->_extendsV[$method]) && $this->_extends[$this->_extendsV[$method]]->$method instanceof \Closure
                ) {
                    return call_user_func_array($this->_extends[$this->_extendsM[$method]]->$method, $args);
                } else {
                    if ($this->$method instanceof \Closure) {
                        return call_user_func_array($this->$method, $args);
                    }
                }
            }
        }

        return false;
        //die();
    }

    public function __get($name)
    {
        $getter = 'get'.$name;
        if (method_exists($this, $getter)) {
            return $this->$getter();
        } else {
            if (isset($this->_extendsM[$getter])) {
                return $this->_extends[$this->_extendsM[$getter]]->$getter();
            } else {
                if (isset($this->_extendsV[$name])) {
                    return $this->_extends[$this->_extendsV[$name]]->$name;
                } else { // if(property_exists($this,$name))
                    return $this->$name;
                }
            }
        }
    }

    public function __set($name, $value)
    {
        $setter = 'set'.$name;
        if (method_exists($this, $setter)) {
            return $this->$setter($value);
        } else {
            if (isset($this->_extendsM[$setter])) {
                return $this->_extends[$this->_extendsM[$setter]]->$setter($value);
            } else {
                if (isset($this->_extendsV[$name])) {
                    return $this->_extends[$this->_extendsV[$name]]->$name = $value;
                } else {
                    return $this->$name = $value;
                }
            }
        }
    }

    public function __isset($name)
    {
        $getter = 'get'.$name;
        if (method_exists($this, $getter)) {
            return $this->$getter() !== null;
        } else {
            if (isset($this->_extendsM[$getter])) {
                return $this->_extends[$this->_extendsM[$getter]]->$getter() !== null;
            } else {
                if (isset($this->_extendsV[$name])) {
                    return $this->_extends[$this->_extendsV[$name]]->$name !== null;
                } else {
                    if (property_exists($this, $name)) {
                        return $this->$name !== null;
                    } else {
                        return false;
                    }
                }
            }
        }
    }

    public function __unset($name)
    {
        $setter = 'set'.$name;
        if (method_exists($this, $setter)) {
            return $this->$setter(null);
        } else {
            if (isset($this->_extendsM[$setter])) {
                return $this->_extends[$this->_extendsM[$setter]]->$setter(null);
            } else {
                if (isset($this->_extendsV[$name])) {
                    return $this->_extends[$this->_extendsV[$name]]->$name = null;
                } else {
                    if (property_exists($this, $name)) {
                        return $this->$name = null;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Determines whether a property is defined.
     * A property is defined if there is a getter or setter method
     * defined in the class. Note, property names are case-insensitive.
     *
     * @param string $name the property name
     *
     * @return boolean whether the property is defined
     * @see canGetProperty
     * @see canSetProperty
     */
    public function hasProperty($name)
    {
        return $this->methodExists('get'.$name) || $this->methodExists('set'.$name);
    }

    public function methodExists($method)
    {
        return method_exists($this, $method) ? true : (isset($this->_extendsM[$method]) ? true : false);
    }

    /**
     * Determines whether a property can be read.
     * A property can be read if the class has a getter method
     * for the property name. Note, property name is case-insensitive.
     *
     * @param string $name the property name
     *
     * @return boolean whether the property can be read
     * @see canSetProperty
     */
    public function canGetProperty($name)
    {
        return $this->methodExists('get'.$name);
    }

    /**
     * Determines whether a property can be set.
     * A property can be written if the class has a setter method
     * for the property name. Note, property name is case-insensitive.
     *
     * @param string $name the property name
     *
     * @return boolean whether the property can be written
     * @see canGetProperty
     */
    public function canSetProperty($name)
    {
        return $this->methodExists('set'.$name);
    }
}

?>
