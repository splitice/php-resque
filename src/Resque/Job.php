<?php

namespace Resque;

use Resque\Component\Job\Model\JobInterface;
use Resque\Component\Job\Model\TrackableJobInterface;
use Resque\Component\Queue\Model\OriginQueueAwareInterface;
use Resque\Component\Queue\Model\QueueInterface;
use Resque\Job\FilterAwareJobInterface;

/**
 * Resque Job
 */
class Job implements
    JobInterface,
    TrackableJobInterface,
    OriginQueueAwareInterface,
    FilterAwareJobInterface
{
    /**
     * @var string Unique identifier of this job
     */
    protected $id;

    /**
     * @var array Array of arguments/parameters.
     */
    protected $arguments;

    /**
     * @var object Instance of the class performing work for this job.
     */
    protected $class;

    /**
     * @var string The current known status/state of this Job.
     */
    protected $state;

    /**
     * @var QueueInterface|null The queue this job was popped from, if it was popped.
     */
    protected $originQueue;

    /**
     * Constructor
     *
     * Instantiate a new instance of a job.
     *
     * @param string $jobClass The fully quantified class name of the target job to run
     * @param array $arguments An array of arguments/parameters for the job.
     */
    public function __construct($jobClass = null, $arguments = array())
    {
        $this->class = $jobClass;
        $this->setArguments($arguments);
    }

    /**
     * @param string $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getId()
    {
        if (null === $this->id) {
            $this->id = md5(uniqid('', true));
        }

        return $this->id;
    }

    /**
     * @param string $class
     * @return $this
     */
    public function setJobClass($class)
    {
        $this->class = $class;

        return $this;
    }

    /**
     * @return object|string
     */
    public function getJobClass()
    {
        return $this->class;
    }

    /**
     * setArguments
     *
     * @param array $args An array of parameters for the job.
     * @throws \InvalidArgumentException when $args is not an array
     */
    public function setArguments($args)
    {
        if (false === is_array($args)) {
            throw new \InvalidArgumentException(
                'Supplied $args must be an array.'
            );
        }

        $this->arguments = $args;
    }

    /**
     * Get the arguments supplied to this job
     *
     * @return array Array of arguments
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * {@inheritDoc}
     */
    public function setState($state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * {@inheritDoc}
     */
    public function setOriginQueue(QueueInterface $queue)
    {
        $this->originQueue = $queue;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getOriginQueue()
    {
        return $this->originQueue;
    }

    /**
     * Generate a string representation used to describe the current job.
     *
     * @return string The string representation of the job.
     */
    public function __toString()
    {
        return 'Job' . $this::encode($this);
    }

    /**
     * Clone
     *
     * On clone, remove the id. This allows recreation of a job via $queue->push(clone $job);
     */
    public function __clone()
    {
        $this->id = null;
    }

    /**
     * {@inheritdoc}
     */
    public static function matchFilter(JobInterface $job, $filter = array())
    {
        $filters = array(
            'id' => function (JobInterface $job, $filter) {
                if (isset($filter['id'])) {

                    return $filter['id'] === $job->getId();
                }

                return null;
            },
            'class' => function (JobInterface $job, $filter) {
                if (isset($filter['class'])) {

                    return $filter['class'] === $job->getJobClass();
                }

                return null;
            },
        );

        $results = array();

        foreach ($filters as $filterName => $filterCallback) {
            $result = $filterCallback($job, $filter);
            // Discard null results as that is the callback telling us it's conditional is not set.
            if (null === $result) {

                continue;
            }

            $results[$filterName] = $result;
        }

        return (count(array_unique($results)) === 1) && reset($results) === true;
    }

    /**
     * Create Redis payload
     *
     * @param \Resque\Component\Job\Model\JobInterface $job
     * @return string JSON object to store in redis.
     */
    public static function encode(JobInterface $job)
    {
        return json_encode(
            array(
                'class' => $job->getJobClass(),
                'args' => array($job->getArguments()),
                'id' => $job->getId(),
                'queue_time' => microtime(true), // @todo this isn't queue time. $queue->push() is queue time.
            )
        );
    }

    public static function decode($payload)
    {
        $payload = json_decode($payload, true);

        // @todo check for json_decode error, if error throw an exception.

        $job = new static();
        $job->setJobClass($payload['class']);
        $job->setArguments($payload['args'][0]);
        $job->setId($payload['id']);

        return $job;
    }
}
