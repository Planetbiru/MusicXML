<?php

namespace MusicXML;

use DateTime;
use DOMNode;
use MusicXML\Map\ModelMap;
use MusicXML\Map\ModelParser;
use MusicXML\Util\PicoAnnotationParser;
use ReflectionClass;

/**
 * MusicXMLWrtiter to write MusicXML document using annotation
 * See https://github.com/Planetbiru/MusicXML
 * 
 * @author Kamshory
 */
class MusicXMLWriter extends \stdClass // NOSONAR
{

    const KEY_PROPERTY_TYPE = "propertyType";
    const KEY_DEFAULT_VALUE = "default_value";
    const KEY_NAME = "name";
    const KEY_VALUE = "value";   

    /**
     * Class params
     *
     * @var array
     */
    private $classParams = array();

    /**
     * Null properties
     *
     * @var array
     */
    private $nullProperties = array();
    
    /**
     * @var string
     */
    private $_objectName = '';
    
    /**
     * @var string
     */
    private $_className = '';

    /**
     * Get null properties
     *
     * @return array
     */
    public function nullPropertiyList()
    {
        return $this->nullProperties;
    }
    
    /**
     * Get object name
     */
    public function objectName()
    {
        return $this->_objectName;
    }

    /**
     * Constructor
     *
     * @param self|array|object|mixed|null $data   Optional initial data. Can be a DOMNode for XML parsing, an array/object for property loading, or a scalar for textContent.
     * @param mixed|null                   $option Optional parameter, currently not used.
     */
    public function __construct($data = null, $option = null, $level = 0)
    {
        $this->_className = get_class($this);
        $jsonAnnot = new PicoAnnotationParser($this->_className);
        $params = $jsonAnnot->getParameters();
        foreach($params as $paramName=>$paramValue)
        {
            $vals = $jsonAnnot->parseKeyValue($paramValue);
            $this->classParams[$paramName] = $vals;
            if($paramName == 'Element' && isset($vals['name']))
            {
                $this->_objectName = $vals['name'];
            }
        }
        if($data !== null)
        {
            if($data instanceof DOMNode)
            {
                $this->loadXml($data, $level);
            }
            else if(($data instanceof DateTime || is_string($data) || is_numeric($data) || is_float($data) || is_integer($data)) 
            && property_exists($this->_className, 'textContent'))
            {
                $this->setTextContent($data);
            }
            else if(is_array($data) || is_object($data))
            {
                $this->loadData($data);
            }
        }
    }
    
    /**
     * Map attribute
     *
     * @return array
     */
    private function mapAttribute()
    {
        return ModelParser::parseModel($this->_className, $this);
    }
    
    /**
     * Load XML data from a DOM node
     *
     * @param \DOMNode $data The DOM node containing attributes and children
     */
    private function loadXml($data, $level = 0)
    {
        // 1. Load attributes from the XML node to the object properties
        if ($data->hasAttributes()) {
            foreach ($data->attributes as $attribute) {
                $attrName = $attribute->nodeName;
                $propName = $this->camelize($attrName, '-');
                if (property_exists($this, $propName) && !is_object($this->{$propName})) {
                    $this->{$propName} = $attribute->nodeValue;
                }
            }
        }

        // 2. Load child elements (as objects) and text content
        foreach ($data->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $trimmedValue = trim($child->nodeValue);
                if ($trimmedValue !== '' && property_exists($this, 'textContent')) {
                    $this->textContent = $trimmedValue;
                }
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $childName = $child->nodeName;
                $propName = $this->camelize($childName, '-');

                // Use ModelMap to find the corresponding class
                if (array_key_exists($childName, ModelMap::CLASS_MAP) 
                    && ModelMap::CLASS_MAP[$childName] !== null) {
                    $className = ModelMap::CLASS_MAP[$childName];
                    $childObject = new $className($child, null, $level + 1); // RECURSIVE CALL
                    
                    // If a specific property for this element exists (e.g., a 'work' property for a 'work' element)
                    if (property_exists($this, $propName)) {
                        $reflectionProp = new \ReflectionProperty(get_class($this), $propName);
                        try {
                            $docComment = $reflectionProp->getDocComment();
                        } catch (\ReflectionException $e) {
                            $docComment = false; // Property might not have a doc comment
                        }
                        $isArray = $docComment && strpos($docComment, '[]') !== false;

                        if ($isArray) {
                            if (!isset($this->{$propName}) || !is_array($this->{$propName})) {
                                $this->{$propName} = array();
                            }
                            $this->{$propName}[] = $childObject;
                        } else {
                            $this->{$propName} = $childObject;
                        }
                    } else if (property_exists($this, 'elements')) {
                        // Fallback for measure content: add to a generic 'elements' array
                        $this->elements[] = $childObject;
                    }
                }
            }
        }
    }
    
    /**
     * Load data to object
     * @param mixed $data Data to load into the object, can be an array or another MusicXMLWriter object.
     * @return self
     */
    public function loadData($data)
    {
        if($data != null)
        {
            if($data instanceof self)
            {
                $values = $data->value();
                foreach ($values as $key => $value) {
                    $key2 = $this->camelize($key);
                    $this->set($key2, $value, true);
                }
            }
            else if (is_array($data) || is_object($data)) {
                foreach ($data as $key => $value) {
                    $key2 = $this->camelize($key);
                    $this->set($key2, $value, true);
                }
            }
        }
        return $this;
    }

    /**
     * Remove property
     *
     * @param object $sourceData    The source object to filter properties from.
     * @param array  $propertyNames An array of property names to keep. All others will be removed.
     * @return mixed
     */
    public function removePropertyExcept($sourceData, $propertyNames)
    {
        if(is_object($sourceData))
        {
            // iterate
            $resultData = new \stdClass;
            foreach($sourceData as $key=>$val)
            {
                if(in_array($key, $propertyNames))
                {
                    $resultData->$key = $val;
                }
            }
            return $resultData;
        }
        if(is_array($sourceData))
        {
            // iterate
            $resultData = array();
            foreach($sourceData as $key=>$val)
            {
                if(in_array($key, $propertyNames))
                {
                    $resultData[$key] = $val;
                }
            }
            return $resultData;
        }
        return new \stdClass;
    }

    /**
     * Convert snake case to camel case
     *
     * @param string $input     The input string in snake_case.
     * @param string $separator The separator character to use (default is '_').
     * @return string
     */
    protected function camelize($input, $separator = '_')
    {
        return lcfirst(str_replace($separator, '', ucwords($input, $separator)));
    }

    /**
     * Convert camel case to snake case
     *
     * @param string $input The input string in camelCase.
     * @param string $glue  The glue character to use for the output (default is '_').
     * @return string
     */
    protected function snakeize($input, $glue = '_') {
        return ltrim(
            preg_replace_callback('/[A-Z]/', function ($matches) use ($glue) {
                return $glue . strtolower($matches[0]);
            }, $input),
            $glue
        );
    } 

    /**
     * Modify null properties
     *
     * @param string $propertyName  The name of the property being modified.
     * @param mixed  $propertyValue The new value of the property.
     * @return void
     */
    private function modifyNullProperties($propertyName, $propertyValue)
    {
        if($propertyValue === null && !isset($this->nullProperties[$propertyName]))
        {
            $this->nullProperties[$propertyName] = true; 
        }
        if($propertyValue != null && isset($this->nullProperties[$propertyName]))
        {
            unset($this->nullProperties[$propertyName]); 
        }
    }

    /**
     * Set property value
     *
     * @param string     $propertyName             The name of the property to set.
     * @param mixed|null $propertyValue            The value to set for the property.
     * @param bool       $skipModifyNullProperties If true, does not track null property assignments.
     * @return self
     */
    public function set($propertyName, $propertyValue, $skipModifyNullProperties = false)
    {
        $var = lcfirst($propertyName);
        $var = $this->camelize($var);
        $this->{$var} = $propertyValue;
        if(!$skipModifyNullProperties && $propertyValue === null)
        {
            $this->modifyNullProperties($var, $propertyValue);
        }
        return $this;
    }
    
    /**
     * Get property value
     *
     * @param string $propertyName The name of the property to get.
     * @return mixed|null
     */
    public function get($propertyName)
    {
        $var = lcfirst($propertyName);
        $var = $this->camelize($var);
        return isset($this->$var) ? $this->$var : null;
    }
    
    /**
     * Get property value 
     *
     * @param string     $propertyName The name of the property to get.
     * @param mixed|null $defaultValue The default value to return if the property is not set.
     * @return mixed|null 
     */
    public function getOrDefault($propertyName, $defaultValue = null)
    {
        $var = lcfirst($propertyName);
        $var = $this->camelize($var);
        return isset($this->$var) ? $this->$var : $defaultValue;
    }
    
    /**
     * Copy value from other object
     *
     * @param self|mixed $source      The source object to copy values from.
     * @param array|null $filter      An optional array of property names to include. If null, all properties are copied.
     * @param bool       $includeNull If true, null values will also be copied.
     * @return void
     */
    public function copyValueFrom($source, $filter = null, $includeNull = false)
    {
        if($filter != null)
        {
            $tmp = array();
            $index = 0;
            foreach($filter as $val)
            {
                $tmp[$index] = trim($this->camelize($val));   
                $index++;
            }
            $filter = $tmp;
        }
        $values = $source->value();
        foreach($values as $property=>$value)
        {
            if(
                ($filter == null || (is_array($filter) && !empty($filter) && in_array($property, $filter))) 
                && 
                ($includeNull || $value != null)
                )
            {
                $this->set($property, $value);
            }
        }
    }

    /**
     * Unset property value
     *
     * @param string $propertyName             The name of the property to unset (set to null).
     * @param bool   $skipModifyNullProperties If true, does not track this as a null property assignment.
     * @return self
     */
    private function removeValue($propertyName, $skipModifyNullProperties = false)
    {
        return $this->set($propertyName, null, $skipModifyNullProperties);
    }
    
    /**
     * Fix value
     *
     * @param string $value The raw string value to be converted.
     * @param string $type  The target data type (e.g., 'string', 'int', 'bool').
     * @return mixed
     */
    protected function fixValue($value, $type) // NOSONAR
    {
        if(strtolower($value) === 'true')
        {
            return true;
        }
        else if(strtolower($value) === 'false')
        {
            return false;
        }
        else if(strtolower($value) === 'null')
        {
            return false;
        }
        else if(is_numeric($value) && strtolower($type) != 'string')
        {
            return $value + 0;
        }
        else 
        {
            return $value;
        }
    }

    /**
     * Get object value
     * 
     * @param bool $snakeCase If true, property names are converted to snake_case.
     * @return \stdClass
     */
    public function value($snakeCase = false)
    {
        $parentProps = $this->propertyList(true, true);
        $value = new \stdClass;
        foreach ($this as $key => $val) {
            if(!in_array($key, $parentProps))
            {
                $value->$key = $val;
            }
        }
        if($snakeCase)
        {
            $value2 = new \stdClass;
            foreach ($value as $key => $val) {
                $key2 = $this->snakeize($key);
                $value2->$key2 = $val;
            }
            return $value2;
        }
        return $value;
    }
    
    /**
     * Get object value
     * 
     * @param bool $snakeCase If true, property names are converted to snake_case.
     * @return \stdClass
     */
    public function valueObject($snakeCase = false)
    {
        return $this->value($snakeCase);
    }

    /**
     * Get object value as associative array
     * 
     * @param bool $snakeCase If true, property names are converted to snake_case.
     * @return array
     */
    public function valueArray($snakeCase = false)
    {
        $value = $this->value($snakeCase);
        return json_decode(json_encode($value), true);
    }
    
    /**
     * Get object value as associated array with upper case first
     *
     * @return array
     */
    public function valueArrayUpperCamel()
    {
        $obj = clone $this;
        $array = (array) $obj->value();
        $renameMap = array();
        $keys = array_keys($array);
        foreach($keys as $key)
        {
            $renameMap[$key] = ucfirst($key);
        }          
        $array = array_combine(array_map(function($el) use ($renameMap) {
            return $renameMap[$el];
        }, array_keys($array)), array_values($array));
        return $array;
    }
    
    /**
     * Check if JSON naming strategy is snake case or not
     *
     * @return bool
     */
    protected function _snake()
    {
        return isset($this->classParams['JSON'])
            && isset($this->classParams['JSON']['property-naming-strategy'])
            && strcasecmp($this->classParams['JSON']['property-naming-strategy'], 'SNAKE_CASE') == 0
            ;
    }
    
    /**
     *  Check if JSON naming strategy is upper camel case or not
     *
     * @return bool
     */
    protected function isUpperCamel()
    {
        return isset($this->classParams['JSON'])
            && isset($this->classParams['JSON']['property-naming-strategy'])
            && strcasecmp($this->classParams['JSON']['property-naming-strategy'], 'UPPER_CAMEL_CASE') == 0
            ;
    }
    
    /**
     * Check if JSON naming strategy is camel case or not
     *
     * @return bool
     */
    protected function _camel()
    {
        return !$this->_snake();
    }

    /**
     * Check if JSON naming strategy is snake case or not
     *
     * @return bool
     */
    protected function _pretty()
    {
        return isset($this->classParams['JSON'])
            && isset($this->classParams['JSON']['prettify'])
            && strcasecmp($this->classParams['JSON']['prettify'], 'true') == 0
            ;
    }
    
    /**
     * Property list
     * 
     * @param bool $reflectSelf  If true, reflects this base class's properties instead of the child's.
     * @param bool $asArrayProps If true, returns an array of property names. Otherwise, returns an array of ReflectionProperty objects.
     * @return array An array of property names or ReflectionProperty objects.
     */
    protected function propertyList($reflectSelf = false, $asArrayProps = false)
    {
        $reflectionClass = $reflectSelf ? self::class : get_called_class();
        $class = new ReflectionClass($reflectionClass);

        // filter only the calling class properties
        // skip parent properties
        $properties = array_filter(
            $class->getProperties(),
            function($property) use($class) {
                return $property->getDeclaringClass()->getName() == $class->getName();
            }
        );
        if($asArrayProps)
        {
            $result = array();
            $index = 0;
            foreach ($properties as $key) {
                $prop = $key->name;
                $result[$index] = $prop;
                
                $index++;
            }
            return $result;
        }
        else
        {
            return $properties;
        }
    }
    
    /**
     * Convert bool to text
     *
     * @param string   $propertyName The name of the boolean property.
     * @param string[] $params       An array with two values: [0] for true, [1] for false.
     * @return string
     */
    private function booleanToTextBy($propertyName, $params)
    {
        $value = $this->get($propertyName);
        if(!isset($value))
        {
            $boolVal = false;
        }
        else
        {
            $boolVal = $value === true || $value == 1 || $value = "1"; 
        }
        return $boolVal?$params[0]:$params[1];
    }
    
    /**
     * Get number of property of the object
     *
     * @return integer
     */
    public function size()
    {
        $parentProps = $this->propertyList(true, true);
        $length = 0;
        foreach ($this as $key => $val) {
            if(!in_array($key, $parentProps))
            {
                $length++;
            }
        }
        return $length;
    }

    /**
     * Magic method called when user call any undefined method. __call method will check the prefix of called method and call appropriated method according to its name and its parameters.
     * is &raquo; get property value as bool. Number will true if it's value is 1. String will be convert to number first. This method not require database connection.
     * get &raquo; get property value. This method not require database connection.
     * set &raquo; set property value. This method not require database connection.
     * unset &raquo; unset property value. This method not require database connection.
     * booleanToTextBy &raquo; convert bool value to yes/no or true/false depend on parameters given. Example: $result = booleanToTextByActive("Yes", "No"); If $obj->active is true, $result will be "Yes" otherwise "No". This method not require database connection.
     * booleanToSelectedBy &raquo; Create attribute selected="selected" for form. This method not require database connection.
     * booleanToCheckedBy &raquo; Create attribute checked="checked" for form. This method not require database connection.
     *
     * @param string $method The name of the method being called.
     * @param mixed  $params An enumerated array of parameters passed to the method.
     * @return mixed|null
     */    
    public function __call($method, $params) // NOSONAR
    {
        if (strncasecmp($method, "hasValue", 8) === 0) {
            $var = lcfirst(substr($method, 8));
            return isset($this->$var);
        } 
        else if (strncasecmp($method, "is", 2) === 0) {
            $var = lcfirst(substr($method, 2));
            return isset($this->$var) ? $this->$var == 1 : false;
        } 
        else if (strncasecmp($method, "equals", 6) === 0) {
            $var = lcfirst(substr($method, 6));
            return isset($this->$var) && $this->$var == $params[0];
        } 
        else if (strncasecmp($method, "get", 3) === 0) {
            $var = lcfirst(substr($method, 3));
            return isset($this->$var) ? $this->$var : null;
        }
        else if (strncasecmp($method, "set", 3) === 0) {
            $var = lcfirst(substr($method, 3));
            $this->$var = $params[0];
            $this->modifyNullProperties($var, $params[0]);
            return $this;
        }
        else if (strncasecmp($method, "unset", 5) === 0) {
            $var = lcfirst(substr($method, 5));
            if(isset($this->{$var}))
            {
                $this->removeValue($var, isset($params[0]) ? $params[0] : false);
            }
            return $this;
        }
        else if (strncasecmp($method, "booleanToTextBy", 15) === 0) {
            $prop = lcfirst(substr($method, 15));
            return $this->booleanToTextBy($prop, $params);
        }
        else if (strncasecmp($method, "booleanToSelectedBy", 19) === 0) {
            $prop = lcfirst(substr($method, 19));
            return $this->booleanToTextBy($prop, array(' selected="selected"', ''));
        }
        else if (strncasecmp($method, "booleanToCheckedBy", 18) === 0) {
            $prop = lcfirst(substr($method, 18));
            return $this->booleanToTextBy($prop, array(' cheked="checked"', ''));
        }     
    }

    /**
     * Magic method to stringify object
     *
     * @return string
     */
    public function __toString()
    {
        $snake = $this->_snake();
        $pretty = $this->_pretty();
        $flag = $pretty ? JSON_PRETTY_PRINT : 0;
        $obj = clone $this;
        foreach($obj as $key=>$value)
        {
            if($value instanceof self)
            {
                $value = $this->stringifyObject($value, $snake);
                $obj->set($key, $value);
            }
        }
        $upperCamel = $this->isUpperCamel();
        if($upperCamel)
        {         
            $value = $this->valueArrayUpperCamel();
            return json_encode($value, $flag);
        }
        else 
        {
            return json_encode($obj->value($snake), $flag);
        }
    }
    
    /**
     * Stringify object
     *
     * @param self $value The MusicXMLWriter object to stringify.
     * @param bool $snake If true, property names are converted to snake_case.
     * @return mixed
     */
    private function stringifyObject($value, $snake)
    {
        if(is_array($value))
        {
            foreach($value as $key2=>$val2)
            {
                if($val2 instanceof self)
                {
                    $value[$key2] = $val2->stringifyObject($val2, $snake);
                }
            }
        }
        else if(is_object($value))
        {
            foreach($value as $key2=>$val2)
            {
                if($val2 instanceof self)
                {
                    
                    $value->{$key2} = $val2->stringifyObject($val2, $snake);
                }
            }
        }
        return $value->value($snake);
    }
    
    /**
     * Get XML
     *
     * @param \DOMDocument $domdoc The parent DOM document.
     * @param string|null  $name   The tag name for the root element of this object.
     * @return DOMNode
     */
    public function toXml($domdoc, $name = null)
    {
        $xmlBuilder = new MusicXMLBuilder($this);
        return $xmlBuilder->toXml($domdoc, $name);
        
    }

    /**
     * Get the value of _objectName
     */ 
    public function getObjectName()
    {
        return $this->_objectName;
    }
}