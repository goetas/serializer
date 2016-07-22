<?php

namespace JMS\Serializer\Tests\Handler;

use JMS\Serializer\Handler\BasicHandler;
use JMS\Serializer\Handler\HandlerRegistryInterface;
use JMS\Serializer\Handler\PropelCollectionHandler;
use JMS\Serializer\SerializerBuilder;

class PropelCollectionHandlerTest extends \PHPUnit_Framework_TestCase
{
    /** @var  $serializer \JMS\Serializer\Serializer */
    private $serializer;

    public function setUp()
    {
        $this->serializer = SerializerBuilder::create()
            ->configureHandlers(function(HandlerRegistryInterface $handlerRegistry){
                $handlerRegistry->registerSubscribingHandler(new BasicHandler());
                $handlerRegistry->registerSubscribingHandler(new PropelCollectionHandler());
            })
            ->build();
    }

    public function testSerializePropelObjectCollection()
    {
        $collection = new \PropelObjectCollection();
        $collection->setData(array(new TestSubject('lolo'), new TestSubject('pepe')));
        $json = $this->serializer->serialize($collection, 'json');

        $data = json_decode($json, true);

        $this->assertCount(2, $data); //will fail if PropelCollectionHandler not loaded

        foreach ($data as $testSubject) {
            $this->assertArrayHasKey('name', $testSubject);
        }
    }
}

class TestSubject
{
    protected $name;

    public function __construct($name)
    {
        $this->name = $name;
    }
}
