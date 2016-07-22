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

use JMS\Serializer\Exception\LogicException;
use JMS\Serializer\Exception\RuntimeException;
use JMS\Serializer\GraphNavigator;

class HandlerRegistry implements HandlerRegistryInterface
{
    protected $handlers;

    public static function getDefaultMethod($direction, $type, $format)
    {
        if (false !== $pos = strrpos($type, '\\')) {
            $type = substr($type, $pos + 1);
        }

        switch ($direction) {
            case GraphNavigator::DIRECTION_DESERIALIZATION:
                return 'deserialize' . $type . 'From' . $format;

            case GraphNavigator::DIRECTION_SERIALIZATION:
                return 'serialize' . $type . 'To' . $format;

            default:
                throw new LogicException(sprintf('The direction %s does not exist; see GraphNavigator::DIRECTION_??? constants.', json_encode($direction)));
        }
    }

    public function __construct(array $handlers = array())
    {
        $this->handlers = $handlers;
    }

    public function registerSubscribingHandler(SubscribingHandlerInterface $handler)
    {
        foreach ($handler->getSubscribingMethods() as $methodData) {
            $directions = array(GraphNavigator::DIRECTION_DESERIALIZATION, GraphNavigator::DIRECTION_SERIALIZATION);
            if (isset($methodData['direction'])) {
                $directions = array($methodData['direction']);
            }

            foreach ($directions as $direction) {
                $method = isset($methodData['method']) ? $methodData['method'] : self::getDefaultMethod($direction, isset($methodData['type']) ? $methodData['type'] : null, isset($methodData['format']) ? $methodData['format'] : null);
                $this->registerHandler($direction, $methodData['type'], isset($methodData['format']) ? $methodData['format'] : null, array($handler, $method));
            }
        }
    }

    public function registerHandler($direction, $typeName, $format, $handler)
    {
        $this->handlers[$direction ?: '*'][$typeName][$format ?: '*'] = $handler;
    }

    public function getHandler($direction, $typeName, $format)
    {
        if (isset($this->handlers[$direction][$typeName][$format])) {
            return $this->handlers[$direction][$typeName][$format];
        }
        if (isset($this->handlers[$direction][$typeName]['*'])) {
            return $this->handlers[$direction][$typeName]['*'];
        }
        if (isset($this->handlers['*'][$typeName][$format])) {
            return $this->handlers['*'][$typeName][$format];
        }
        if (isset($this->handlers['*'][$typeName]['*'])) {
            return $this->handlers['*'][$typeName]['*'];
        }
        return null;

    }
}
