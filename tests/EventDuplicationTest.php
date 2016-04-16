<?php

namespace go1\edge;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit_Framework_TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class EventDuplicationTest extends PHPUnit_Framework_TestCase
{
    public function testEvent()
    {
        $connection = DriverManager::getConnection(['url' => 'sqlite://sqlite::memory:']);
        $edge = new Edge($connection, 'edge', 111, $dispatcher = new EventDispatcher());
        $edge->install();

        $dispatcher->addListener(Edge::EVENT_LINK_DUPLICATE, function (EdgeEvent $event) use ($edge) {
            $this->assertEquals($edge, $event->getSubject());
            $this->assertEquals(111, $event['type']);
            $this->assertEquals(555, $event['source_id']);
            $this->assertEquals(999, $event['target_id']);
            $this->assertEquals(1, $event['weight']);
        });

        $edge->link(555, 999, 0);
        $edge->link(555, 999, 1);
    }
}
