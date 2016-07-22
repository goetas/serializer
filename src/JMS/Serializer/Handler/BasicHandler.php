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
use JMS\Serializer\Exception\RuntimeException;
use JMS\Serializer\Graph\GraphNavigator;
use JMS\Serializer\TypeDefinition;
use JMS\Serializer\VisitorInterface;

class BasicHandler implements SubscribingHandlerInterface
{
    public static function getSubscribingMethods()
    {
        $methods = array();
        $types = array('integer', 'NULL', 'string', 'boolean', 'double', 'array');

        foreach ($types as $type) {
            $methods[] = array(
                'type' => $type,
                'method' => 'visitGeneric',
            );
        }
        $methods[] = array(
            'type' => 'float',
            'method' => 'visitFloat',
        );
        $methods[] = array(
            'type' => 'resource',
            'method' => 'visitResource',
        );

        return $methods;
    }

    public function visitGeneric(VisitorInterface $visitor, $data, TypeDefinition $type, Context $context)
    {
        return $visitor->{'visit' . $type->getName()}($data, $type, $context);
    }

    public function visitFloat(VisitorInterface $visitor, $data, TypeDefinition $type, Context $context)
    {
        return $visitor->visitDouble($data, $type, $context);
    }

    public function visitResource(VisitorInterface $visitor, $data, TypeDefinition $type, Context $context)
    {
        $msg = 'Resources are not supported in serialized data.';
        if ($context->getDirection() === GraphNavigator::DIRECTION_SERIALIZATION && null !== $path = $context->getPath()) {
            $msg .= ' Path: ' . $path;
        }
        throw new RuntimeException($msg);
    }

}
