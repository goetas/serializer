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

namespace JMS\Serializer\Graph;

use JMS\Serializer\Construction\ObjectConstructorInterface;
use JMS\Serializer\Context;
use JMS\Serializer\EventDispatcher\EventDispatcherInterface;
use JMS\Serializer\EventDispatcher\PreDeserializeEvent;
use JMS\Serializer\Exception\RuntimeException;
use JMS\Serializer\Handler\HandlerRegistryInterface;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\TypeDefinition;
use Metadata\MetadataFactoryInterface;

/**
 * Handles traversal along the object graph.
 *
 * This class handles traversal along the graph, and calls different methods
 * on visitors, or custom handlers to process its nodes.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
final class DeserializerGraphNavigator extends GraphNavigator
{
    private $objectConstructor;

    public function __construct(MetadataFactoryInterface $metadataFactory, HandlerRegistryInterface $handlerRegistry, ObjectConstructorInterface $objectConstructor, EventDispatcherInterface $dispatcher = null)
    {
        parent::__construct($metadataFactory, $handlerRegistry, $dispatcher);
        $this->objectConstructor = $objectConstructor;
    }

    /**
     * Called for each node of the graph that is being traversed.
     *
     * @param mixed $data the data depends on the direction, and type of visitor
     * @param null|TypeDefinition $type array has the format ["name" => string, "params" => array]
     *
     * @param Context $context
     * @return mixed the return value depends on the direction, and type of visitor
     */
    public function accept($data, TypeDefinition $type = null, Context $context)
    {
        $visitor = $context->getVisitor();

        // If the type was not given, we infer the most specific type from the
        // input data in serialization mode.
        if (null === $type) {
            throw new RuntimeException('The type must be given for all properties when deserializing.');
        }


        $context->increaseDepth();

        // Trigger pre-serialization callbacks, and listeners if they exist.
        // Dispatch pre-serialization event before handling data to have ability change type in listener
        if ($this->hasListener('pre', $type->getName(), $context)) {
            $event = new PreDeserializeEvent($context, $data, $type);
            $this->dispatch('pre', $type->getName(), $context, $event);

            $type = $event->getType();
            $data = $event->getData();
        }
        // First, try whether a custom handler exists for the given type. This is done
        // before loading metadata because the type name might not be a class, but
        // could also simply be an artifical type.
        if (null !== $handler = $this->handlerRegistry->getHandler($context->getDirection(), $type->getName(), $context->getFormat())) {
            $rs = call_user_func($handler, $visitor, $data, $type, $context);
            $this->leaveScope($context, $data);

            return $rs;
        }

        $exclusionStrategy = $context->getExclusionStrategy();

        /** @var $metadata ClassMetadata */
        $metadata = $this->metadataFactory->getMetadataForClass($type->getName());

        if (!empty($metadata->discriminatorMap) && $type->getName() === $metadata->discriminatorBaseClass) {
            $metadata = $this->resolveMetadata($data, $metadata);
        }

        if (null !== $exclusionStrategy && $exclusionStrategy->shouldSkipClass($metadata, $context)) {
            $this->leaveScope($context, $data);

            return null;
        }

        $context->pushClassMetadata($metadata);

        $object = $this->objectConstructor->construct($visitor, $metadata, $data, $type, $context);

        $visitor->startVisitingObject($metadata, $object, $type, $context);
        foreach ($metadata->propertyMetadata as $propertyMetadata) {
            if (null !== $exclusionStrategy && $exclusionStrategy->shouldSkipProperty($propertyMetadata, $context)) {
                continue;
            }

            if ($propertyMetadata->readOnly) {
                continue;
            }

            $context->pushPropertyMetadata($propertyMetadata);
            $visitor->visitProperty($propertyMetadata, $data, $context);
            $context->popPropertyMetadata();
        }

        $rs = $visitor->endVisitingObject($metadata, $data, $type, $context);
        $this->afterVisitingObject($metadata, $rs, $type, $context);

        return $rs;
    }

    protected function leaveScope(Context $context, $data)
    {
        $context->decreaseDepth();
    }

    private function resolveMetadata($data, ClassMetadata $metadata)
    {
        switch (true) {
            case is_array($data) && isset($data[$metadata->discriminatorFieldName]):
                $typeValue = (string)$data[$metadata->discriminatorFieldName];
                break;

            case is_object($data) && isset($data->{$metadata->discriminatorFieldName}):
                $typeValue = (string)$data->{$metadata->discriminatorFieldName};
                break;

            default:
                throw new \LogicException(sprintf(
                    'The discriminator field name "%s" for base-class "%s" was not found in input data.',
                    $metadata->discriminatorFieldName,
                    $metadata->name
                ));
        }

        if (!isset($metadata->discriminatorMap[$typeValue])) {
            throw new \LogicException(sprintf(
                'The type value "%s" does not exist in the discriminator map of class "%s". Available types: %s',
                $typeValue,
                $metadata->name,
                implode(', ', array_keys($metadata->discriminatorMap))
            ));
        }

        return $this->metadataFactory->getMetadataForClass($metadata->discriminatorMap[$typeValue]);
    }

}
