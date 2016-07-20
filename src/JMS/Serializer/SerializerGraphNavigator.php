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

namespace JMS\Serializer;

use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\EventDispatcher\PreSerializeEvent;
use JMS\Serializer\Exception\RuntimeException;
use JMS\Serializer\Metadata\ClassMetadata;

/**
 * Handles traversal along the object graph.
 *
 * This class handles traversal along the graph, and calls different methods
 * on visitors, or custom handlers to process its nodes.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
final class SerializerGraphNavigator extends AbstractGraphNavigator
{

    protected function getDirection()
    {
        return 'serialize';
    }

    protected function findType($data, $type)
    {
        if (null === $data) {
            // If the data is null, we have to force the type to null regardless of the input in order to
            // guarantee correct handling of null values, and not have any internal auto-casting behavior.
            return array('name' => 'NULL', 'params' => array());
        }

        if (null === $type) {
            $typeName = gettype($data);
            if ('object' === $typeName) {
                $typeName = get_class($data);
            }
            $type = array('name' => $typeName, 'params' => array());
        }

        // If we're serializing a polymorphic type, then we'll be interested in the
        // metadata for the actual type of the object, not the base class.
        if (class_exists($type['name'], false) || interface_exists($type['name'], false)) {
            if (is_subclass_of($data, $type['name'], false)) {
                $type = array('name' => get_class($data), 'params' => array());
            }
        }

        return $type;
    }
    /**
     * Called for each node of the graph that is being traversed.
     *
     * @param mixed $data the data depends on the direction, and type of visitor
     * @param null|array $type array has the format ["name" => string, "params" => array]
     *
     * @return mixed the return value depends on the direction, and type of visitor
     */
    public function accept($data, array $type = null, Context $context)
    {
        $visitor = $context->getVisitor();
        
        $type = $this->findType($data, $type);

        switch ($type['name']) {
            case 'NULL':
                return $visitor->visitNull($data, $type, $context);

            case 'string':
                return $visitor->visitString($data, $type, $context);

            case 'integer':
                return $visitor->visitInteger($data, $type, $context);

            case 'boolean':
                return $visitor->visitBoolean($data, $type, $context);

            case 'double':
            case 'float':
                return $visitor->visitDouble($data, $type, $context);

            case 'array':
                return $visitor->visitArray($data, $type, $context);
            default:
                

                
                if ($this->hasListeners('pre', $type['name'], $context)) {
                    $event = new PreSerializeEvent($context, $data, $type);
                    $this->dispatch('pre', $type['name'], $context, $event);
                    $type = $event->getType();
                }

                if (null !== $handler = $this->handlerRegistry->getHandler($context->getDirection(), $type['name'], $context->getFormat())) {
                    $rs = call_user_func($handler, $visitor, $data, $type, $context);
                    //$context->stopVisiting($data);

                    return $rs;
                }
                
                $metadata = $this->getMetadataForClass($type, $data, $context);
                
                $exclusionStrategy = $context->getExclusionStrategy();
                if (null !== $exclusionStrategy && $exclusionStrategy->shouldSkipClass($metadata, $context)) {
                    $context->stopVisiting($data);
                    return null;
                }



                $context->pushClassMetadata($metadata);
                
                $object = $this->prepareObject($data, $visitor, $metadata, $data, $type, $context);

                if (!$context->startVisiting($data)){
                    return null;
                }
                foreach ($this->getMethods('pre', $metadata) as $method) {
                    $method->invoke($data);
                }
                
                $visitor->startVisitingObject($metadata, $object, $type, $context);
                foreach ($metadata->propertyMetadata as $propertyMetadata) {
                    if (null !== $exclusionStrategy && $exclusionStrategy->shouldSkipProperty($propertyMetadata, $context)) {
                        continue;
                    }

                    $context->pushPropertyMetadata($propertyMetadata);
                    $visitor->visitProperty($propertyMetadata, $data, $context);
                    $context->popPropertyMetadata();
                }
                
                $this->afterVisitingObject($metadata, $object, $type, $context);
                return $visitor->endVisitingObject($metadata, $data, $type, $context);
        }
    }
}
