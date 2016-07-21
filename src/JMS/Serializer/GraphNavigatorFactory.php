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

use JMS\Serializer\Construction\ObjectConstructorInterface;
use JMS\Serializer\Handler\HandlerRegistryInterface;
use Metadata\MetadataFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Handles traversal along the object graph.
 *
 * This class handles traversal along the graph, and calls different methods
 * on visitors, or custom handlers to process its nodes.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class GraphNavigatorFactory
{

    private $dispatcher;
    private $metadataFactory;
    private $handlerRegistry;
    private $objectConstructor;

    public function __construct(MetadataFactoryInterface $metadataFactory, HandlerRegistryInterface $handlerRegistry, ObjectConstructorInterface $objectConstructor, EventDispatcherInterface $dispatcher = null)
    {
        $this->dispatcher = $dispatcher ?: new EventDispatcher();
        $this->metadataFactory = $metadataFactory;
        $this->handlerRegistry = $handlerRegistry;
        $this->objectConstructor = $objectConstructor;
    }

    /**
     * @param int $direction
     * @return GraphNavigator
     */
    public function getGraphNavigator($direction = GraphNavigator::DIRECTION_SERIALIZATION)
    {
        if ($direction == GraphNavigator::DIRECTION_DESERIALIZATION) {
            return new DeserializerGraphNavigator($this->metadataFactory, $this->handlerRegistry, $this->objectConstructor, $this->dispatcher);
        } else {
            return new SerializerGraphNavigator($this->metadataFactory, $this->handlerRegistry, $this->dispatcher);
        }
    }
}
