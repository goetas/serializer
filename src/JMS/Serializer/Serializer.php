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
use JMS\Serializer\EventDispatcher\EventDispatcherInterface;
use JMS\Serializer\Exception\RuntimeException;
use JMS\Serializer\Exception\UnsupportedFormatException;
use JMS\Serializer\Handler\HandlerRegistryInterface;
use Metadata\MetadataFactoryInterface;

/**
 * Serializer Implementation.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Serializer implements SerializerInterface
{
    private $factory;
    private $handlerRegistry;
    private $objectConstructor;
    private $dispatcher;
    private $typeParser;

    /** @var array */
    private $serializationVisitors;

    /** @var array */
    private $deserializationVisitors;

    /**
     * @var GraphNavigatorFactory
     */
    private $navigatorFactory;

    /**
     * Constructor.
     *
     * @param \Metadata\MetadataFactoryInterface $factory
     * @param Handler\HandlerRegistryInterface $handlerRegistry
     * @param Construction\ObjectConstructorInterface $objectConstructor
     * @param array $serializationVisitors of VisitorInterface
     * @param array $deserializationVisitors of VisitorInterface
     * @param EventDispatcher\EventDispatcherInterface $dispatcher
     * @param TypeParser $typeParser
     */
    public function __construct(MetadataFactoryInterface $factory, HandlerRegistryInterface $handlerRegistry, ObjectConstructorInterface $objectConstructor, array $serializationVisitors, array $deserializationVisitors, EventDispatcherInterface $dispatcher = null, TypeParser $typeParser = null)
    {
        $this->factory = $factory;
        $this->handlerRegistry = $handlerRegistry;
        $this->objectConstructor = $objectConstructor;
        $this->dispatcher = $dispatcher;
        $this->typeParser = $typeParser ?: new TypeParser();
        $this->serializationVisitors = $serializationVisitors;
        $this->deserializationVisitors = $deserializationVisitors;

        $this->navigatorFactory = new GraphNavigatorFactory($this->factory, $this->handlerRegistry, $this->objectConstructor, $this->dispatcher);
    }

    private function callVisitor($array, $format, Context $context, $callback)
    {
        if (!isset($array[$format])){
            throw new UnsupportedFormatException(sprintf('The format "%s" is not supported for %s.', $format, $context->getDirection()));
        }
        
        return call_user_func($callback, $array[$format]);

    }

    public function serialize($data, $format, SerializationContext $context = null)
    {
        if (null === $context) {
            $context = new SerializationContext();
        }

        return $this->callVisitor($this->serializationVisitors, $format, $context, function (VisitorInterface $visitor) use ($context, $data, $format) {
            $this->visit($visitor, $context, $visitor->prepare($data), $format);

            return $visitor->getResult();
        });
    }

    public function deserialize($data, $type, $format, DeserializationContext $context = null)
    {
        if (null === $context) {
            $context = new DeserializationContext();
        }

        return $this->callVisitor($this->deserializationVisitors, $format, $context, function (VisitorInterface $visitor) use ($context, $data, $format, $type) {
            $preparedData = $visitor->prepare($data);
            $navigatorResult = $this->visit($visitor, $context, $preparedData, $format, $this->typeParser->parse($type));

            return $this->handleDeserializeResult($visitor->getResult(), $navigatorResult);
        });
    }

    /**
     * Converts objects to an array structure.
     *
     * This is useful when the data needs to be passed on to other methods which expect array data.
     *
     * @param mixed $data anything that converts to an array, typically an object or an array of objects
     *
     * @param SerializationContext $context
     * @return array
     */
    public function toArray($data, SerializationContext $context = null)
    {
        if (null === $context) {
            $context = new SerializationContext();
        }

        return $this->callVisitor($this->serializationVisitors, 'json', $context, function (JsonSerializationVisitor $visitor) use ($context, $data) {
            $this->visit($visitor, $context, $data, 'json');
            $result = $this->convertArrayObjects($visitor->getRoot());

            if (!is_array($result)) {
                throw new RuntimeException(sprintf(
                    'The input data of type "%s" did not convert to an array, but got a result of type "%s".',
                    is_object($data) ? get_class($data) : gettype($data),
                    is_object($result) ? get_class($result) : gettype($result)
                ));
            }

            return $result;
        });
    }

    /**
     * Restores objects from an array structure.
     *
     * @param array $data
     * @param string $type
     *
     * @return mixed this returns whatever the passed type is, typically an object or an array of objects
     */
    public function fromArray(array $data, $type, DeserializationContext $context = null)
    {
        if (null === $context) {
            $context = new DeserializationContext();
        }

        return $this->callVisitor($this->deserializationVisitors, 'json', $context, function (JsonDeserializationVisitor $visitor) use ($data, $type, $context) {
            $navigatorResult = $this->visit($visitor, $context, $data, 'json', $this->typeParser->parse($type));

            return $this->handleDeserializeResult($visitor->getResult(), $navigatorResult);
        });
    }

    private function visit(VisitorInterface $visitor, Context $context, $data, $format, TypeDefinition $type = null)
    {
        $navigator = $this->navigatorFactory->getGraphNavigator($context->getDirection());
        $context->initialize(
            $format,
            $visitor,
            $navigator,
            $this->factory
        );

        $visitor->setNavigator($navigator);

        return $navigator->accept($data, $type, $context);
    }

    private function handleDeserializeResult($visitorResult, $navigatorResult)
    {
        // This is a special case if the root is handled by a callback on the object itself.
        if (null === $visitorResult && null !== $navigatorResult) {
            return $navigatorResult;
        }

        return $visitorResult;
    }

    private function convertArrayObjects($data)
    {
        if ($data instanceof \ArrayObject) {
            $data = (array)$data;
        }
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->convertArrayObjects($v);
            }
        }

        return $data;
    }

    /**
     * @return MetadataFactoryInterface
     */
    public function getMetadataFactory()
    {
        return $this->factory;
    }
}
