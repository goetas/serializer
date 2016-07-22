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
use JMS\Serializer\Graph\GraphNavigator;
use JMS\Serializer\TypeDefinition;
use JMS\Serializer\VisitorInterface;
use PropelCollection;

class PropelCollectionHandler implements SubscribingHandlerInterface
{
    public static function getSubscribingMethods()
    {
        $methods = array();
        $formats = array('json', 'xml', 'yml');
        //Note: issue when handling inheritance
        $collectionTypes = array(
            'PropelCollection',
            'PropelObjectCollection',
            'PropelArrayCollection',
            'PropelOnDemandCollection'
        );

        foreach ($collectionTypes as $type) {
            foreach ($formats as $format) {
                $methods[] = array(
                    'direction' => GraphNavigator::DIRECTION_SERIALIZATION,
                    'type' => $type,
                    'format' => $format,
                    'method' => 'serializeCollection',
                );

                $methods[] = array(
                    'direction' => GraphNavigator::DIRECTION_DESERIALIZATION,
                    'type' => $type,
                    'format' => $format,
                    'method' => 'deserializeCollection',
                );
            }
        }

        return $methods;
    }

    public function serializeCollection(VisitorInterface $visitor, PropelCollection $collection, TypeDefinition $type, Context $context)
    {
        // We change the base type, and pass through possible parameters.
        $type = new TypeDefinition('array', $type->getParams());

        return $visitor->visitArray($collection->getData(), $type, $context);
    }

    public function deserializeCollection(VisitorInterface $visitor, $data, TypeDefinition $type, Context $context)
    {
        // See above. Set parameter type to PropelCollection<T> or PropelCollection<K,V>
        $type = new TypeDefinition('array', $type->getParams());

        $collection = new PropelCollection();
        $collection->setData($visitor->visitArray($data, $type, $context));

        return $collection;
    }
}
