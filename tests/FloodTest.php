<?php

namespace go1\flood;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class FloodTest extends \PHPUnit_Framework_TestCase
{
    /** @var  Connection */
    private $connection;

    /** @var  Flood */
    private $flood;

    public function setUp()
    {
        $this->connection = DriverManager::getConnection(['url' => 'sqlite://sqlite::memory:']);
        $this->flood = new Flood($this->connection);
        $this->flood->install();
    }

    public function testInit()
    {
        $this->assertTrue($this->flood instanceof Flood);
    }

    public function testRegister()
    {
        $this->flood->register(__METHOD__);
        $item = $this->connection->executeQuery('SELECT * FROM flood')->fetchAll()[0];

        $this->assertEquals(__METHOD__, $item['event']);
        $this->assertLessThanOrEqual(time(), $item['timestamp']);
        $this->assertLessThanOrEqual(time() + 3600, $item['expiration']);
    }

    public function testDisallowed()
    {
        $this->flood->register(__METHOD__);
        $this->flood->register(__METHOD__);
        $this->flood->register(__METHOD__);

        $this->assertFalse($this->flood->isAllowed(__METHOD__, 3));
        $this->assertTrue($this->flood->isAllowed(__METHOD__, 4));
    }

    public function testClearEvent()
    {
        $sql = 'SELECT COUNT(*) FROM flood WHERE event = ?';

        $this->flood->register(__METHOD__);
        $this->assertEquals(1, $this->connection->executeQuery($sql, [__METHOD__])->fetchColumn(), 'Before clear, found 1.');
        $this->flood->clearEvent(__METHOD__);
        $this->assertEquals(0, $this->connection->executeQuery($sql, [__METHOD__])->fetchColumn(), 'Before clear, found 0.');
    }

    public function testClear()
    {
        $sql = 'SELECT COUNT(*) FROM flood WHERE 1';

        $this->flood->register('event_1');
        $this->flood->register('event_2');
        $this->assertEquals(2, $this->connection->executeQuery($sql)->fetchColumn(), 'Before clear, found 2.');
        $this->flood->clear(time() + 3600);
        $this->assertEquals(0, $this->connection->executeQuery($sql)->fetchColumn(), 'Before clear, found 0.');
    }
}
