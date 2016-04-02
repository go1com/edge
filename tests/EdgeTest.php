<?php

namespace go1\edge;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit_Framework_TestCase;

class EdgeTest extends PHPUnit_Framework_TestCase
{
    /** @var  Connection */
    private $connection;

    /** @var Edge */
    private $edge;

    public function setUp()
    {
        $this->connection = DriverManager::getConnection(['url' => 'sqlite://sqlite::memory:']);
        $this->edge = new Edge($this->connection, 'edge', 111);
        $this->edge->install();
    }

    public function testInit()
    {
        $this->assertTrue($this->edge instanceof Edge);
    }

    public function testLink()
    {
        $this->edge->link(555, 777, 0);
        $this->edge->link(555, 999, 1);

        $ros = $this->connection->executeQuery('SELECT * FROM edge WHERE type = 111')->fetchAll();
        $this->assertEquals(777, $ros[0]['target_id']);
        $this->assertEquals(999, $ros[1]['target_id']);
    }

    public function testGetLoadTargetIds()
    {
        $this->edge->link(555, 999, 1);
        $this->edge->link(555, 777, 0);
        $this->assertEquals([777, 999], $this->edge->getTargetIds(555));
    }

    public function testGetLoadingSourceIds()
    {
        $this->edge->link(555, 999, 1);
        $this->edge->link(555, 777, 0);

        $this->assertEquals([555], $this->edge->getSourceIds(777));
        $this->assertEquals([555], $this->edge->getSourceIds(999));
        $this->assertEquals([777 => [555], 999 => [555]], $this->edge->getSourceIds([777, 999]));
    }

    public function testClearSource()
    {
        $this->edge->link(555, 999);
        $this->edge->link(777, 555);
        $this->edge->clearUsingSource(555);
        $this->edge->clearUsingTarget(555);
        $this->assertEquals(0, $this->connection->executeQuery('SELECT COUNT(*) FROM edge')->fetchColumn());
    }
}
