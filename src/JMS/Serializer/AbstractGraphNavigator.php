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
use JMS\Serializer\Handler\HandlerRegistryInterface;
use JMS\Serializer\EventDispatcher\EventDispatcherInterface;
use JMS\Serializer\Metadata\ClassMetadata;
use Metadata\MetadataFactoryInterface;

/**
 * Handles traversal along the object graph.
 *
 * This class handles traversal along the graph, and calls different methods
 * on visitors, or custom handlers to process its nodes.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class AbstractGraphNavigator extends GraphNavigator
{

    protected $dispatcher;
    protected $metadataFactory;
    protected $handlerRegistry;

    public function __construct(MetadataFactoryInterface $metadataFactory, HandlerRegistryInterface $handlerRegistry, EventDispatcherInterface $dispatcher = null)
    {
        $this->dispatcher = $dispatcher;
        $this->metadataFactory = $metadataFactory;
        $this->handlerRegistry = $handlerRegistry;
    }

    protected function getMethods($type, ClassMetadata $metadata)
    {
        $propertyName = $type .  ucfirst($this->getDirection()). 'Methods';
        if (property_exists($metadata, $propertyName)) {
            return $metadata->{$propertyName};
        } else {
            return [];
        }
    }

    protected abstract function getDirection();

    protected function findType($data, $type)
    {

/*
 * @todo
            $type['name'] == 'resource'
                $msg = 'Resources are not supported in serialized data.';
                if ($context instanceof SerializationContext && null !== $path = $context->getPath()) {
                    $msg .= ' Path: '.$path;
                }

                throw new RuntimeException($msg);
  */
        if (null === $type) {
            throw new RuntimeException('The type must be given for all properties when deserializing.');
        }
        return $type;
    }

    protected function hasListeners($type, $name, Context $context)
    {
        if (!$this->dispatcher){
            return false;
        }
        return $this->dispatcher->hasListeners('serializer.'.$type.'_'.$this->getDirection(), $name, $context->getFormat());
    }

    protected function dispatch($type, $name, Context $context, ObjectEvent $event)
    {
        return $this->dispatcher->dispatch('serializer.'.$type.'_'.$this->getDirection(), $name, $context->getFormat(), $event);
    }

    protected function getMetadataForClass($type, $data, Context $context)
    {
        return $this->metadataFactory->getMetadataForClass($type['name']);
    }

    protected function afterVisitingObject(ClassMetadata $metadata, $object, array $type, Context $context)
    {
        $context->stopVisiting($object);
        $context->popClassMetadata();

        foreach ($this->getMethods('post', $metadata) as $method) {
            $method->invoke($object);
        }

        if ($this->hasListeners('post', $metadata->name, $context)) {
            $this->dispatch('post', $metadata->name, $context, new ObjectEvent($context, $object, $type));
        }
    }


    protected function prepareObject($data, $visitor, $metadata, $data, $type, $context)
    {
        return $data;
    }
}
