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

use JMS\Serializer\EventDispatcher\Event;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\Handler\HandlerRegistryInterface;
use JMS\Serializer\EventDispatcher\EventDispatcherInterface;
use JMS\Serializer\Metadata\ClassMetadata;
use Metadata\MetadataFactoryInterface;
use JMS\Serializer\Exception\InvalidArgumentException;

/**
 * Handles traversal along the object graph.
 *
 * This class handles traversal along the graph, and calls different methods
 * on visitors, or custom handlers to process its nodes.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class GraphNavigator
{
    const DIRECTION_SERIALIZATION = 1;
    const DIRECTION_DESERIALIZATION = 2;

    protected $dispatcher;
    protected $metadataFactory;
    protected $handlerRegistry;

    /**
     * Parses a direction string to one of the direction constants.
     *
     * @param string $dirStr
     *
     * @return integer
     */
    public static function parseDirection($dirStr)
    {
        switch (strtolower($dirStr)) {
            case 'serialization':
                return self::DIRECTION_SERIALIZATION;

            case 'deserialization':
                return self::DIRECTION_DESERIALIZATION;

            default:
                throw new InvalidArgumentException(sprintf('The direction "%s" does not exist.', $dirStr));
        }
    }

    protected function callLifecycleMethods($when, ClassMetadata $metadata, Context $context, $object)
    {
        $direction = $context->getDirection() == self::DIRECTION_SERIALIZATION ? 'Serialize' : 'Deserialize' ;

        $propName = $when . $direction . 'Methods';


        if (!property_exists($metadata, $propName)){
            return ;
        }
        
        foreach ($metadata->{$propName} as $method){
            $method->invoke($object);
        }
    }

    public function __construct(MetadataFactoryInterface $metadataFactory, HandlerRegistryInterface $handlerRegistry, EventDispatcherInterface $dispatcher = null)
    {
        $this->dispatcher = $dispatcher;
        $this->metadataFactory = $metadataFactory;
        $this->handlerRegistry = $handlerRegistry;
    }

    /**
     * Called for each node of the graph that is being traversed.
     *
     * @param mixed $data the data depends on the direction, and type of visitor
     * @param null|array $type array has the format ["name" => string, "params" => array]
     *
     * @return mixed the return value depends on the direction, and type of visitor
     */
    public abstract function accept($data, array $type = null, Context $context);
    protected abstract function leaveScope(Context $context, $data);

    protected function hasListener($type, $typeName, Context $context)
    {
        $eventName = $context->getDirection() == self::DIRECTION_DESERIALIZATION ? 'deserialize' : 'serialize';

        return null !== $this->dispatcher && $this->dispatcher->hasListeners('serializer.'.$type.'_'. $eventName, $typeName, $context->getFormat());
    }

    protected function dispatch($type, $typeName, Context $context, Event $event)
    {
        $eventName = $context->getDirection() == self::DIRECTION_DESERIALIZATION ? 'deserialize' : 'serialize';

        $this->dispatcher->dispatch('serializer.'.$type.'_'.$eventName, $typeName, $context->getFormat(), $event);
    }

    protected function afterVisitingObject(ClassMetadata $metadata, $object, array $type, Context $context)
    {
        $this->leaveScope($context, $object);
        $context->popClassMetadata();

        $this->callLifecycleMethods('post', $metadata, $context, $object);

        if ($this->hasListener('post', $type['name'], $context)) {
            $this->dispatch('post', $metadata->name, $context, new ObjectEvent($context, $object, $type));
        }
    }
}
