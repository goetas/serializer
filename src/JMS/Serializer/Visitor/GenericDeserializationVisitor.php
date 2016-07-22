<?php

/*
 * Copyright 2013 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\Serializer\Visitor;

use JMS\Serializer\Context;
use JMS\Serializer\Exception\RuntimeException;
use JMS\Serializer\Graph\GraphNavigator;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Metadata\PropertyMetadata;
use JMS\Serializer\TypeDefinition;

/**
 * Generic Deserialization Visitor.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class GenericDeserializationVisitor extends AbstractVisitor
{
    private $navigator;
    private $result;
    private $objectStack;
    private $currentObject;

    public function setNavigator(GraphNavigator $navigator)
    {
        $this->navigator = $navigator;
        $this->result = null;
        $this->objectStack = new \SplStack;
    }
    
    public function prepare($data)
    {
        return $this->decode($data);
    }

    public function visitNull($data, TypeDefinition $type, Context $context)
    {
        return null;
    }

    public function visitString($data, TypeDefinition $type, Context $context)
    {
        $data = (string)$data;

        if (null === $this->result) {
            $this->result = $data;
        }

        return $data;
    }

    public function visitBoolean($data, TypeDefinition $type, Context $context)
    {
        $data = (Boolean)$data;

        if (null === $this->result) {
            $this->result = $data;
        }

        return $data;
    }

    public function visitInteger($data, TypeDefinition $type, Context $context)
    {
        $data = (integer)$data;

        if (null === $this->result) {
            $this->result = $data;
        }

        return $data;
    }

    public function visitDouble($data, TypeDefinition $type, Context $context)
    {
        $data = (double)$data;

        if (null === $this->result) {
            $this->result = $data;
        }

        return $data;
    }

    public function visitArray($data, TypeDefinition $type, Context $context)
    {
        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Expected array, but got %s: %s', gettype($data), json_encode($data)));
        }

        // If no further parameters were given, keys/values are just passed as is.
        if (!$type->getParams()) {
            if (null === $this->result) {
                $this->result = $data;
            }

            return $data;
        }

        switch (count($type->getParams())) {
            case 1: // Array is a list.
                $listType = $type->getParam(0);

                $result = array();
                if (null === $this->result) {
                    $this->result = &$result;
                }

                foreach ($data as $v) {
                    $result[] = $this->navigator->accept($v, $listType, $context);
                }

                return $result;

            case 2: // Array is a map.
                list($keyType, $entryType) = $type->getParams();

                $result = array();
                if (null === $this->result) {
                    $this->result = &$result;
                }

                foreach ($data as $k => $v) {
                    $result[$this->navigator->accept($k, $keyType, $context)] = $this->navigator->accept($v, $entryType, $context);
                }

                return $result;

            default:
                throw new RuntimeException(sprintf('Array type cannot have more than 2 parameters, but got %s.', json_encode($type['params'])));
        }
    }

    public function startVisitingObject(ClassMetadata $metadata, $object, TypeDefinition $type, Context $context)
    {
        $this->setCurrentObject($object);

        if (null === $this->result) {
            $this->result = $this->currentObject;
        }
    }

    public function visitProperty(PropertyMetadata $metadata, $data, Context $context)
    {
        $name = $this->namingStrategy->translateName($metadata);

        if (null === $data || !array_key_exists($name, $data)) {
            return;
        }

        if (!$metadata->type) {
            throw new RuntimeException(sprintf('You must define a type for %s::$%s.', $metadata->class, $metadata->name));
        }

        $v = $data[$name] !== null ? $this->navigator->accept($data[$name], $metadata->type, $context) : null;

        $metadata->setValue($this->currentObject, $v);
    }

    public function endVisitingObject(ClassMetadata $metadata, $data, TypeDefinition $type, Context $context)
    {
        $obj = $this->currentObject;
        $this->revertCurrentObject();

        return $obj;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function setCurrentObject($object)
    {
        $this->objectStack->push($this->currentObject);
        $this->currentObject = $object;
    }

    public function getCurrentObject()
    {
        return $this->currentObject;
    }

    public function revertCurrentObject()
    {
        return $this->currentObject = $this->objectStack->pop();
    }

    abstract protected function decode($str);
}
