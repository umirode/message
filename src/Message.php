<?php
/**
 * Created by IntelliJ IDEA.
 * User: maksim
 * Date: 2020-02-10
 * Time: 10:02
 */

namespace Umirode\Message;

use Jawira\CaseConverter\Convert;
use Laminas\Code\Reflection\ClassReflection;
use Laminas\Code\Reflection\DocBlockReflection;

/**
 * Class Message
 *
 * @package Umirode\Message
 */
abstract class Message
{
    /**
     * Message constructor.
     *
     * @param array|null $data
     */
    final public function __construct(?array $data = [])
    {
        $this->load($data ?? []);
    }

    /**
     * @param array $data
     */
    private function load(array $data): void
    {
        try {
            $reflection = new ClassReflection($this);

            foreach ($reflection->getProperties() as $property) {
                $property = $this->getPropertyData($property);
                if ($property === null) {
                    continue;
                }

                [$propertyName, $key, $type, $isArray] = $property;

                if (!isset($data[$key])) {
                    continue;
                }

                if ($isArray) {
                    $this->handleArrayValue($data, $type, $key, $propertyName);
                    return;
                }

                $this->handleValue($data, $type, $key, $propertyName);
            }
        } catch (\Exception $e) {
            throw new \InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param \ReflectionProperty $property
     *
     * @return array|null
     * @throws \Jawira\CaseConverter\CaseConverterException
     */
    private function getPropertyData(\ReflectionProperty $property): ?array
    {
        if ($property->getDocComment() === false) {
            return null;
        }

        $propertyName = $property->getName();
        $key = (new Convert($propertyName))->toSnake();

        $docComment = new DocBlockReflection($property->getDocComment());

        $type = $docComment->getTag('var')->getTypes()[0] ?? null;
        if ($type === null) {
            return null;
        }

        $isArray = strpos($type, '[]') !== false || $type === 'array';
        if ($isArray) {
            $type = str_replace('[]', '', $type);
        }

        return [$propertyName, $key, $type, $isArray];
    }

    private function setType($value, string $type)
    {
        $isNumericType = in_array($type, ['float', 'double', 'int', 'integer'], true);
        if ($isNumericType && !is_numeric($value)) {
            return $value;
        }

        settype($value, $type);
        return $value;
    }

    /**
     * @param array  $data
     * @param string $type
     * @param string $key
     * @param string $propertyName
     */
    private function handleValue(array $data, string $type, string $key, string $propertyName): void
    {
        $this->{$propertyName} = $this->setType($data[$key], $type);
    }

    /**
     * @param array  $data
     * @param string $type
     * @param string $key
     * @param string $propertyName
     */
    private function handleArrayValue(array $data, string $type, string $key, string $propertyName): void
    {
        $values = $data[$key] ?? [];

        if (is_string($values)) {
            $values = json_decode($values);
        }

        if (!is_array($values) || empty($values)) {
            $this->{$propertyName} = [];
            return;
        }

        if ($type === 'array') {
            $this->{$propertyName} = $values;
            return;
        }

        foreach ($values as $valueKey => $value) {
            $this->{$propertyName}[$valueKey] = $this->setType($value, $type);
        }
    }
}
