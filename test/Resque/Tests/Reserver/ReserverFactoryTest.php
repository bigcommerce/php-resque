<?php

namespace Resque\Tests\Reserver;

use Resque\Reserver\ReserverFactory;
use Resque;
use PHPUnit\Framework\Attributes\DataProvider;

class ReserverFactoryTest extends \PHPUnit\Framework\TestCase
{
    private function getFactory(): ReserverFactory
    {
        return new ReserverFactory(new \Resque_Log());
    }

    public function testCreateReserverFromNameThrowsExceptionForUnknownReserver()
    {
        $this->expectException(\Resque\Reserver\UnknownReserverException::class);
        $this->expectExceptionMessage('Unknown reserver \'foo\'');
        $this->getFactory()->createReserverFromName('foo', array());
    }

    public static function createReserverFromNameDataProvider(): array
    {
        return array(
            array('queue_order', '\Resque\Reserver\QueueOrderReserver'),
            array('RANDOM_QUEUE_ORDER', '\Resque\Reserver\RandomQueueOrderReserver'),
            array('Blocking_List_Pop', '\Resque\Reserver\BlockingListPopReserver'),
        );
    }

    #[DataProvider('createReserverFromNameDataProvider')]
    public function testCreateReserverFromNameCreatesExpectedReserver($name, $expectedReserver)
    {
        $queues = array(
            'queue_a',
            'queue_b',
            'queue_c',
            'queue_d',
        );

        $reserver = $this->getFactory()->createReserverFromName($name, $queues);
        $this->assertInstanceOf($expectedReserver, $reserver);

        // account for shuffling by RandomQueueOrderReserver
        $actualQueues = $reserver->getQueues();
        sort($actualQueues);

        $this->assertEquals($queues, $actualQueues);
    }

    public function testCreateDefaultReserverCreatesExpectedReserver()
    {
        $reserver = $this->getFactory()->createDefaultReserver(array());
        $this->assertInstanceOf('\Resque\Reserver\QueueOrderReserver', $reserver);
    }

    public static function createReserverFromEnvironmentDataProvider(): array
    {
        return array(
            array(array('BLOCKING=1'), '\Resque\Reserver\BlockingListPopReserver'),
            array(array('BLOCKING=0', 'RESERVER=random_queue_order'), '\Resque\Reserver\RandomQueueOrderReserver'),
            array(array('BLOCKING=', 'RESERVER=random_queue_order'), '\Resque\Reserver\RandomQueueOrderReserver'),
            array(array('RESERVER=Queue_Order'), '\Resque\Reserver\QueueOrderReserver'),
            array(array(), '\Resque\Reserver\QueueOrderReserver'),
        );
    }

    #[DataProvider('createReserverFromEnvironmentDataProvider')]
    public function testCreateReserverFromEnvironmentCreatesExpectedReserver($env, $expectedReserver)
    {
        $queues = array(
            'queue_a',
            'queue_b',
            'queue_c',
            'queue_d',
        );

        foreach ($env as $var) {
            putenv($var);
        }

        $reserver = $this->getFactory()->createReserverFromEnvironment($queues);
        $this->assertInstanceOf($expectedReserver, $reserver);

        // account for shuffling by RandomQueueOrderReserver
        $actualQueues = $reserver->getQueues();
        sort($actualQueues);

        $this->assertEquals($queues, $actualQueues);

        putenv('BLOCKING');
        putenv('RESERVER');
    }

    public function testCreateReserverFromEnvironmentThrowsExceptionForUnknownReserver()
    {
        $this->expectException(\Resque\Reserver\UnknownReserverException::class);
        $this->expectExceptionMessage('Unknown reserver \'foobar\'');
        putenv('RESERVER=foobar');
        $this->getFactory()->createReserverFromEnvironment(array());
        putenv('RESERVER');
    }
}
