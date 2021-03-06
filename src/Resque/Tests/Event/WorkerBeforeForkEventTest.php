<?php

namespace Resque\Tests\Event;

use Resque\Event\WorkerBeforeForkEvent;

class WorkerBeforeForkEventTest extends \PHPUnit_Framework_TestCase
{
    public function testEvent()
    {
        $worker = $this->getMock('Resque\Component\Worker\Model\WorkerInterface');
        $job = $this->getMock('Resque\Component\Job\Model\JobInterface');
        $event = new WorkerBeforeForkEvent($worker, $job);
        $this->assertEquals($worker, $event->getWorker());
        $this->assertEquals($job, $event->getJob());
    }
}
