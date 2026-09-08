<?php

defined('SYSPATH') or die('No direct access allowed.');

use Aws\Sqs\SqsClient;

class CQueue_Queue_SqsQueue extends CQueue_AbstractQueue {
    /**
     * The Amazon SQS instance.
     *
     * @var \Aws\Sqs\SqsClient
     */
    protected $sqs;

    /**
     * The name of the default queue.
     *
     * @var string
     */
    protected $default;

    /**
     * The queue URL prefix.
     *
     * @var string
     */
    protected $prefix;

    /**
     * The queue URL suffix.
     *
     * @var string
     */
    protected $suffix;

    /**
     * Seconds to long-poll on receiveMessage(). 0 keeps the previous short-polling behavior.
     *
     * @var int
     */
    protected $waitTimeSeconds;

    /**
     * Messages to request per receiveMessage() call. 1 keeps the previous one-at-a-time
     * behavior (no MaxNumberOfMessages key sent at all).
     *
     * @var int
     */
    protected $maxNumberOfMessages;

    /**
     * Messages already fetched from AWS but not yet handed out by pop(), keyed by queue URL.
     *
     * @var array
     */
    protected $buffer = [];

    /**
     * Create a new Amazon SQS queue instance.
     *
     * @param \Aws\Sqs\SqsClient $sqs
     * @param string             $default
     * @param string             $prefix
     * @param bool               $dispatchAfterCommit
     * @param mixed              $suffix
     * @param int                $waitTimeSeconds
     * @param int                $maxNumberOfMessages
     *
     * @return void
     */
    public function __construct(SqsClient $sqs, $default, $prefix = '', $suffix = '', $dispatchAfterCommit = false, $waitTimeSeconds = 0, $maxNumberOfMessages = 1) {
        $this->sqs = $sqs;
        $this->prefix = $prefix;
        $this->suffix = $suffix;
        $this->default = $default;
        $this->dispatchAfterCommit = $dispatchAfterCommit;
        $this->waitTimeSeconds = $waitTimeSeconds;
        $this->maxNumberOfMessages = $maxNumberOfMessages;
    }

    /**
     * Get the size of the queue.
     *
     * @param null|string $queue
     *
     * @return int
     */
    public function size($queue = null) {
        $response = $this->sqs->getQueueAttributes([
            'QueueUrl' => $this->getQueue($queue),
            'AttributeNames' => ['ApproximateNumberOfMessages'],
        ]);
        $attributes = $response->get('Attributes');

        return (int) $attributes['ApproximateNumberOfMessages'];
    }

    /**
     * Push a new job onto the queue.
     *
     * @param string      $job
     * @param mixed       $data
     * @param null|string $queue
     *
     * @return mixed
     */
    public function push($job, $data = '', $queue = null) {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue ?: $this->default, $data),
            $queue,
            null,
            function ($payload, $queue) {
                return $this->pushRaw($payload, $queue);
            }
        );
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param string      $payload
     * @param null|string $queue
     * @param array       $options
     *
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = []) {
        return $this->sqs->sendMessage([
            'QueueUrl' => $this->getQueue($queue), 'MessageBody' => $payload,
        ])->get('MessageId');
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @param string                               $job
     * @param mixed                                $data
     * @param null|string                          $queue
     *
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null) {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue ?: $this->default, $data),
            $queue,
            $delay,
            function ($payload, $queue, $delay) {
                return $this->sqs->sendMessage([
                    'QueueUrl' => $this->getQueue($queue),
                    'MessageBody' => $payload,
                    'DelaySeconds' => $this->secondsUntil($delay),
                ])->get('MessageId');
            }
        );
    }

    /**
     * Push an array of jobs onto the queue.
     *
     * @param array       $jobs
     * @param mixed       $data
     * @param null|string $queue
     *
     * @return void
     */
    public function bulk($jobs, $data = '', $queue = null) {
        foreach ((array) $jobs as $job) {
            if (isset($job->delay)) {
                $this->later($job->delay, $job, $data, $queue);
            } else {
                $this->push($job, $data, $queue);
            }
        }
    }

    /**
     * Pop the next job off of the queue.
     *
     * A prior receiveMessage() call may have fetched more than one message (see
     * $maxNumberOfMessages) -- serve those from the buffer before calling AWS again, so a
     * higher $maxNumberOfMessages actually reduces the number of receiveMessage() calls
     * instead of just discarding the extra messages.
     *
     * @param null|string $queue
     *
     * @return null|\CQueue_JobInterface
     */
    public function pop($queue = null) {
        $queue = $this->getQueue($queue);

        if (!empty($this->buffer[$queue])) {
            return $this->jobFromMessage(array_shift($this->buffer[$queue]), $queue);
        }

        $params = [
            'QueueUrl' => $queue,
            'AttributeNames' => ['ApproximateReceiveCount'],
        ];
        if ($this->waitTimeSeconds > 0) {
            $params['WaitTimeSeconds'] = $this->waitTimeSeconds;
        }
        if ($this->maxNumberOfMessages > 1) {
            $params['MaxNumberOfMessages'] = min(10, $this->maxNumberOfMessages);
        }
        $response = $this->sqs->receiveMessage($params);
        if (!is_null($response['Messages']) && count($response['Messages']) > 0) {
            $messages = $response['Messages'];
            $first = array_shift($messages);
            if (count($messages) > 0) {
                $this->buffer[$queue] = $messages;
            }

            return $this->jobFromMessage($first, $queue);
        }
    }

    /**
     * @param array  $message
     * @param string $queue
     *
     * @return \CQueue_JobInterface
     */
    protected function jobFromMessage($message, $queue) {
        return new CQueue_Job_SqsJob(
            $this->container,
            $this->sqs,
            $message,
            $this->connectionName,
            $queue
        );
    }

    /**
     * Get the queue or return the default.
     *
     * @param null|string $queue
     *
     * @return string
     */
    public function getQueue($queue) {
        $queue = $queue ?: $this->default;

        return filter_var($queue, FILTER_VALIDATE_URL) === false ? rtrim($this->prefix, '/') . '/' . $queue : $queue;
    }

    /**
     * Get the underlying SQS instance.
     *
     * @return \Aws\Sqs\SqsClient
     */
    public function getSqs() {
        return $this->sqs;
    }
}
