<?php
namespace trident;

class Object
{
    public function __construct(array $options = [])
    {
        //$options = DI::prepareArguments($options,true);
        foreach ($options as $property => $value) {
            if (is_array($this->$property) && is_array($value)) {
                $this->$property = \array_replace_recursive($this->$property, $value);
            } else {
                $this->$property = $value;
            }
        }
        $this->init($options);
    }

    public function init($options = null)
    {
    }

    /**
     * @return string the fully qualified name of this class.
     */
    public static function className()
    {
        return get_called_class();
    }
}
