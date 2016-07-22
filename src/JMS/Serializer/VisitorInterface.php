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

use JMS\Serializer\Graph\GraphNavigator;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Metadata\PropertyMetadata;

/**
 * Interface for visitors.
 *
 * This contains the minimal set of values that must be supported for any
 * output format.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface VisitorInterface
{
    /**
     * Allows visitors to convert the input data to a different representation
     * before the actual serialization/deserialization process starts.
     *
     * @param mixed $data
     *
     * @return mixed
     */
    public function prepare($data);

    /**
     * @param mixed $data
     * @param TypeDefinition $type
     *
     * @return mixed
     */
    public function visitNull($data, TypeDefinition $type, Context $context);

    /**
     * @param mixed $data
     * @param TypeDefinition $type
     *
     * @return mixed
     */
    public function visitString($data, TypeDefinition $type, Context $context);

    /**
     * @param mixed $data
     * @param TypeDefinition $type
     *
     * @return mixed
     */
    public function visitBoolean($data, TypeDefinition $type, Context $context);

    /**
     * @param mixed $data
     * @param TypeDefinition $type
     *
     * @return mixed
     */
    public function visitDouble($data, TypeDefinition $type, Context $context);

    /**
     * @param mixed $data
     * @param TypeDefinition $type
     *
     * @return mixed
     */
    public function visitInteger($data, TypeDefinition $type, Context $context);

    /**
     * @param mixed $data
     * @param TypeDefinition $type
     *
     * @return mixed
     */
    public function visitArray($data, TypeDefinition $type, Context $context);

    /**
     * Called before the properties of the object are being visited.
     *
     * @param ClassMetadata $metadata
     * @param mixed $data
     * @param TypeDefinition $type
     *
     * @return void
     */
    public function startVisitingObject(ClassMetadata $metadata, $data, TypeDefinition $type, Context $context);

    /**
     * @param PropertyMetadata $metadata
     * @param mixed $data
     *
     * @return void
     */
    public function visitProperty(PropertyMetadata $metadata, $data, Context $context);

    /**
     * Called after all properties of the object have been visited.
     *
     * @param ClassMetadata $metadata
     * @param mixed $data
     * @param TypeDefinition $type
     *
     * @return mixed
     */
    public function endVisitingObject(ClassMetadata $metadata, $data, TypeDefinition $type, Context $context);

    /**
     * Called before serialization/deserialization starts.
     *
     * @param GraphNavigator $navigator
     *
     * @return void
     */
    public function setNavigator(GraphNavigator $navigator);

    /**
     * @return object|array|scalar
     */
    public function getResult();
}
