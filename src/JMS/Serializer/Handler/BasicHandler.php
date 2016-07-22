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

namespace JMS\Serializer\Handler;

use JMS\Serializer\Context;
use JMS\Serializer\JsonDeserializationVisitor;
use JMS\Serializer\TypeDefinition;
use JMS\Serializer\XmlDeserializationVisitor;
use JMS\Serializer\Exception\RuntimeException;
use JMS\Serializer\VisitorInterface;
use JMS\Serializer\GraphNavigator;
use JMS\Serializer\XmlSerializationVisitor;

class BasicHandler implements SubscribingHandlerInterface
{
    public static function getSubscribingMethods()
    {
        $methods = array();
        $types = array('integer', 'NULL', 'string', 'boolean', 'double', 'array');

        $directions = [
            GraphNavigator::DIRECTION_SERIALIZATION,
            GraphNavigator::DIRECTION_DESERIALIZATION
        ];

        foreach ($directions as $direction) {
            foreach (array('json', 'xml', 'yml') as $format) {
                foreach ($types as $type) {
                    $methods[] = array(
                        'type' => $type,
                        'format' => $format,
                        'direction' => $direction,
                        'method' => 'visitGeneric',
                    );
                }
                $methods[] = array(
                    'type' => 'float',
                    'format' => $format,
                    'direction' => $direction,
                    'method' => 'visitFloat',
                );
                $methods[] = array(
                    'type' => 'resource',
                    'format' => $format,
                    'direction' => $direction,
                    'method' => 'visitResource',
                );
            }
        }
        return $methods;
    }

    public function visitGeneric(VisitorInterface $visitor, $data, TypeDefinition $type, Context $context)
    {
        return $visitor->{'visit'.$type->getName()}($data, $type, $context);
    }

    public function visitFloat(VisitorInterface $visitor, $data, TypeDefinition $type, Context $context)
    {
        return $visitor->visitDouble($data, $type, $context);
    }

    public function visitResource(VisitorInterface $visitor, $data, TypeDefinition $type, Context $context)
    {
        $msg = 'Resources are not supported in serialized data.';
        if ($context->getDirection() === GraphNavigator::DIRECTION_SERIALIZATION && null !== $path = $context->getPath()) {
            $msg .= ' Path: '.$path;
        }
        throw new RuntimeException($msg);
    }

}
