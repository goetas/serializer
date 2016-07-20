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
use JMS\Serializer\EventDispatcher\PreDeserializeEvent;
use JMS\Serializer\Exception\RuntimeException;
use JMS\Serializer\Construction\ObjectConstructorInterface;
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
final class DeserializerGraphNavigator extends AbstractGraphNavigator
{
    
    private $objectConstructor;

    protected function getDirection()
    {
        return 'deserialize';
    }

    public function __construct(MetadataFactoryInterface $metadataFactory, HandlerRegistryInterface $handlerRegistry, ObjectConstructorInterface $objectConstructor, EventDispatcherInterface $dispatcher = null)
    {
        parent::__construct($metadataFactory, $handlerRegistry, $dispatcher);
        $this->objectConstructor = $objectConstructor;
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
                var_dump($data);
                return $visitor->visitArray($data, $type, $context);

            default:

                if ($this->hasListeners('pre', $type['name'], $context)) {
                    $event = new PreDeserializeEvent($context, $data, $type);
                    $this->dispatch('pre', $type['name'], $context, $event);
                    $type = $event->getType();
                    $data = $event->getData();
                }
                
                if (null !== $handler = $this->handlerRegistry->getHandler($context->getDirection(), $type['name'], $context->getFormat())) {
                    $rs = call_user_func($handler, $visitor, $data, $type, $context);
                    return $rs;
                }

                $metadata = $this->getMetadataForClass($type, $data, $context);
                var_dump($type, $data);
                if (!$context->startVisiting($data)){
                    return null;
                }

                $exclusionStrategy = $context->getExclusionStrategy();
                if (null !== $exclusionStrategy && $exclusionStrategy->shouldSkipClass($metadata, $context)) {
                    $context->stopVisiting($data);
                    return null;
                }

                $context->pushClassMetadata($metadata);

                $object = $this->prepareObject($data, $visitor, $metadata, $data, $type, $context);

                foreach ($this->getMethods('pre', $metadata) as $method) {
                    $method->invoke($data);
                }

                $visitor->startVisitingObject($metadata, $object, $type, $context);
                foreach ($metadata->propertyMetadata as $propertyMetadata) {
                    if ($propertyMetadata->readOnly || null !== $exclusionStrategy && $exclusionStrategy->shouldSkipProperty($propertyMetadata, $context)) {
                        continue;
                    }

                    $context->pushPropertyMetadata($propertyMetadata);
                    $visitor->visitProperty($propertyMetadata, $data, $context);
                    $context->popPropertyMetadata();
                }

                $this->afterVisitingObject($metadata, $data, $type, $context);
                return $visitor->endVisitingObject($metadata, $data, $type, $context);
        }
    }

    protected function prepareObject($data, $visitor, $metadata, $data, $type, $context)
    {
        return $this->objectConstructor->construct($visitor, $metadata, $data, $type, $context);;
    }

    protected function getMetadataForClass($type, $data, Context $context)
    {
        $metadata = parent::getMetadataForClass($type, $data, $context);

        if (! empty($metadata->discriminatorMap) && $type['name'] === $metadata->discriminatorBaseClass) {
            $metadata = $this->resolveMetadata($context, $data, $metadata);
        }

        return $metadata;
    }

    private function resolveMetadata(DeserializationContext $context, $data, ClassMetadata $metadata)
    {
        switch (true) {
            case is_array($data) && isset($data[$metadata->discriminatorFieldName]):
                $typeValue = (string) $data[$metadata->discriminatorFieldName];
                break;

            case is_object($data) && isset($data->{$metadata->discriminatorFieldName}):
                $typeValue = (string) $data->{$metadata->discriminatorFieldName};
                break;

            default:
                throw new \LogicException(sprintf(
                    'The discriminator field name "%s" for base-class "%s" was not found in input data.',
                    $metadata->discriminatorFieldName,
                    $metadata->name
                ));
        }

        if ( ! isset($metadata->discriminatorMap[$typeValue])) {
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
