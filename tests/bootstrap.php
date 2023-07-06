<?php

/**
 * Set up test helpers here
 *
 * Putting some stuff in global scope cuz its ok... its ok... relax... its ok...
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

if (!function_exists('mock')) {
    /**
     * Shortcut to mock an item
     *
     * @return \Tests\CustomMockInterface
     */
    function mock()
    {
        return call_user_func_array('Mockery::mock', func_get_args());
    }
}

if (!function_exists('callMethod')) {
    /**
     * Call protected or private method
     *
     * @param $object
     * @param $methodName
     * @param array $arguments
     * @return mixed
     * @throws ReflectionException
     */
    function callMethod($object, $methodName, array $arguments = [])
    {
        $class = new ReflectionClass($object);
        $method = $class->getMethod($methodName);
        $method->setAccessible(true);
        return empty($arguments) ? $method->invoke($object) : $method->invokeArgs($object, $arguments);
    }
}

if (!function_exists('callStaticMethod')) {
    /**
     * @param $class
     * @param $methodName
     * @param array $arguments
     * @return mixed
     * @throws ReflectionException
     */
    function callStaticMethod($class, $methodName, array $arguments = [])
    {
        $class = new ReflectionClass($class);
        $method = $class->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs(null, $arguments);
    }
}

if (!function_exists('getProperty')) {
    /**
     * Get protected or private property
     *
     * @param $object
     * @param $propertyName
     * @return mixed
     * @throws ReflectionException
     */
    function getProperty($object, $propertyName)
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
}

if (!function_exists('setProperty')) {
    /**
     * Set a protected or private property
     *
     * @param $object
     * @param $propertyName
     * @param $propertyValue
     * @throws ReflectionException
     */
    function setProperty($object, $propertyName, $propertyValue)
    {
        $reflectionClass = new ReflectionClass($object);
        $property = $reflectionClass->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $propertyValue);
        $property->setAccessible(false);
    }
}

if (!function_exists('getConstant')) {
    /**
     * Get protected or private constant
     */
    function getConstant($object, string $constantName)
    {
        $reflection = new ReflectionClass($object);
        return $reflection->getConstant($constantName);
    }
}
