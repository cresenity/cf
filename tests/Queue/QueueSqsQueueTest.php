<?php

use Aws\Result;
use Mockery as m;
use Aws\Sqs\SqsClient;
use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class QueueSqsQueueTest extends TestCase {
    use MockeryPHPUnitIntegration;

    /**
     * @var string
     */
    protected $prefix = 'https://sqs.ap-southeast-1.amazonaws.com/1234567890';

    /**
     * @var string
     */
    protected $queueName = 'antrean-uji';

    /**
     * @return string
     */
    protected function queueUrl() {
        return $this->prefix . '/' . $this->queueName;
    }

    /**
     * @param \Mockery\MockInterface $client
     * @param int                    $waitTimeSeconds
     * @param int                    $maxNumberOfMessages
     *
     * @return CQueue_Queue_SqsQueue
     */
    protected function makeQueue($client, $waitTimeSeconds = 0, $maxNumberOfMessages = 1) {
        $queue = new CQueue_Queue_SqsQueue($client, $this->queueName, $this->prefix, '', false, $waitTimeSeconds, $maxNumberOfMessages);
        $queue->setContainer(CContainer::getInstance());
        $queue->setConnectionName('sqs');

        return $queue;
    }

    /**
     * @param string $id
     *
     * @return array
     */
    protected function message($id) {
        return [
            'MessageId' => $id,
            'ReceiptHandle' => 'tanda-terima-' . $id,
            'Body' => json_encode(['job' => 'PekerjaanUji', 'data' => []]),
            'Attributes' => ['ApproximateReceiveCount' => 1],
        ];
    }

    public function testSizeReadsTheApproximateMessageCount() {
        $client = m::mock(SqsClient::class);
        $client->shouldReceive('getQueueAttributes')->once()->with(m::on(function ($args) {
            return $args['QueueUrl'] === $this->queueUrl()
                && $args['AttributeNames'] === ['ApproximateNumberOfMessages'];
        }))->andReturn(new Result(['Attributes' => ['ApproximateNumberOfMessages' => 7]]));

        $this->assertSame(7, $this->makeQueue($client)->size());
    }

    public function testPushSendsThePayloadAndReturnsTheMessageId() {
        $client = m::mock(SqsClient::class);
        $client->shouldReceive('sendMessage')->once()->with(m::on(function ($args) {
            $payload = json_decode($args['MessageBody'], true);

            return $args['QueueUrl'] === $this->queueUrl()
                && $payload['job'] === 'PekerjaanUji'
                && $payload['data'] === ['id' => 1];
        }))->andReturn(new Result(['MessageId' => 'pesan-1']));

        $this->assertSame('pesan-1', $this->makeQueue($client)->push('PekerjaanUji', ['id' => 1]));
    }

    public function testPushRawSendsTheBodyUntouched() {
        $client = m::mock(SqsClient::class);
        $client->shouldReceive('sendMessage')->once()->with([
            'QueueUrl' => $this->queueUrl(),
            'MessageBody' => 'mentah',
        ])->andReturn(new Result(['MessageId' => 'pesan-2']));

        $this->assertSame('pesan-2', $this->makeQueue($client)->pushRaw('mentah'));
    }

    /**
     * A delay becomes DelaySeconds, which is what makes a job invisible until
     * its time comes.
     */
    public function testLaterSendsTheDelayAsDelaySeconds() {
        $client = m::mock(SqsClient::class);
        $client->shouldReceive('sendMessage')->once()->with(m::on(function ($args) {
            return $args['DelaySeconds'] === 60;
        }))->andReturn(new Result(['MessageId' => 'pesan-3']));

        $this->assertSame('pesan-3', $this->makeQueue($client)->later(60, 'PekerjaanUji'));
    }

    public function testPushOnUsesTheNamedQueue() {
        $client = m::mock(SqsClient::class);
        $client->shouldReceive('sendMessage')->once()->with(m::on(function ($args) {
            return $args['QueueUrl'] === $this->prefix . '/lainnya';
        }))->andReturn(new Result(['MessageId' => 'pesan-4']));

        $this->makeQueue($client)->pushOn('lainnya', 'PekerjaanUji');
    }

    public function testPopReturnsASqsJobWhenAMessageArrives() {
        $client = m::mock(SqsClient::class);
        $client->shouldReceive('receiveMessage')->once()->andReturn(new Result([
            'Messages' => [
                [
                    'MessageId' => 'pesan-5',
                    'ReceiptHandle' => 'tanda-terima',
                    'Body' => json_encode(['job' => 'PekerjaanUji', 'data' => []]),
                    'Attributes' => ['ApproximateReceiveCount' => 1],
                ],
            ],
        ]));

        $job = $this->makeQueue($client)->pop();

        $this->assertInstanceOf(CQueue_Job_SqsJob::class, $job);
        $this->assertSame('sqs', $job->getConnectionName());
    }

    public function testPopReturnsNothingWhenTheQueueIsEmpty() {
        $client = m::mock(SqsClient::class);
        $client->shouldReceive('receiveMessage')->once()->andReturn(new Result(['Messages' => null]));

        $this->assertNull($this->makeQueue($client)->pop());
    }

    /**
     * Default behavior (waitTimeSeconds not configured) must stay byte-identical to before
     * long-polling support existed -- no WaitTimeSeconds key at all, not even zero.
     */
    public function testPopOmitsWaitTimeSecondsByDefault() {
        $client = m::mock(SqsClient::class);
        $client->shouldReceive('receiveMessage')->once()->with(m::on(function ($args) {
            return !array_key_exists('WaitTimeSeconds', $args);
        }))->andReturn(new Result(['Messages' => null]));

        $this->makeQueue($client)->pop();
    }

    public function testPopSendsWaitTimeSecondsWhenConfigured() {
        $client = m::mock(SqsClient::class);
        $client->shouldReceive('receiveMessage')->once()->with(m::on(function ($args) {
            return isset($args['WaitTimeSeconds']) && $args['WaitTimeSeconds'] === 15;
        }))->andReturn(new Result(['Messages' => null]));

        $this->makeQueue($client, 15)->pop();
    }

    /**
     * Default behavior (maxNumberOfMessages not configured) must stay byte-identical to
     * before batching support existed -- no MaxNumberOfMessages key at all, not even one.
     */
    public function testPopOmitsMaxNumberOfMessagesByDefault() {
        $client = m::mock(SqsClient::class);
        $client->shouldReceive('receiveMessage')->once()->with(m::on(function ($args) {
            return !array_key_exists('MaxNumberOfMessages', $args);
        }))->andReturn(new Result(['Messages' => null]));

        $this->makeQueue($client)->pop();
    }

    public function testPopSendsMaxNumberOfMessagesWhenConfigured() {
        $client = m::mock(SqsClient::class);
        $client->shouldReceive('receiveMessage')->once()->with(m::on(function ($args) {
            return isset($args['MaxNumberOfMessages']) && $args['MaxNumberOfMessages'] === 5;
        }))->andReturn(new Result(['Messages' => null]));

        $this->makeQueue($client, 0, 5)->pop();
    }

    /**
     * AWS caps a single receiveMessage() call at 10 messages -- a larger config value must
     * be clamped, not sent through as is (AWS would reject it).
     */
    public function testPopClampsMaxNumberOfMessagesToTen() {
        $client = m::mock(SqsClient::class);
        $client->shouldReceive('receiveMessage')->once()->with(m::on(function ($args) {
            return $args['MaxNumberOfMessages'] === 10;
        }))->andReturn(new Result(['Messages' => null]));

        $this->makeQueue($client, 0, 50)->pop();
    }

    /**
     * A batch of N messages from one receiveMessage() call must be handed out one at a time
     * across N pop() calls, with only the first pop() actually hitting AWS -- this is the
     * whole point of MaxNumberOfMessages: fewer API calls for the same messages processed.
     */
    public function testPopServesABatchFromOneReceiveMessageCallAcrossMultiplePops() {
        $client = m::mock(SqsClient::class);
        $client->shouldReceive('receiveMessage')->once()->andReturn(new Result([
            'Messages' => [$this->message('a'), $this->message('b'), $this->message('c')],
        ]));

        $queue = $this->makeQueue($client, 0, 10);

        $first = $queue->pop();
        $second = $queue->pop();
        $third = $queue->pop();

        $this->assertSame('a', $first->getJobId());
        $this->assertSame('b', $second->getJobId());
        $this->assertSame('c', $third->getJobId());
    }

    /**
     * Once the buffer from a batch is exhausted, the next pop() must go back to AWS instead
     * of returning null forever.
     */
    public function testPopFetchesAgainAfterTheBufferIsExhausted() {
        $client = m::mock(SqsClient::class);
        $client->shouldReceive('receiveMessage')->once()->andReturn(new Result([
            'Messages' => [$this->message('a')],
        ]));
        $client->shouldReceive('receiveMessage')->once()->andReturn(new Result([
            'Messages' => [$this->message('b')],
        ]));

        $queue = $this->makeQueue($client, 0, 10);

        $this->assertSame('a', $queue->pop()->getJobId());
        $this->assertSame('b', $queue->pop()->getJobId());
    }

    public function testGetQueueBuildsTheUrlFromThePrefix() {
        $queue = $this->makeQueue(m::mock(SqsClient::class));

        $this->assertSame($this->queueUrl(), $queue->getQueue(null));
        $this->assertSame($this->prefix . '/lainnya', $queue->getQueue('lainnya'));
    }

    /**
     * A queue given as a full URL is used as is, so a queue on another account
     * or region can be addressed directly.
     */
    public function testAFullUrlIsUsedUnchanged() {
        $queue = $this->makeQueue(m::mock(SqsClient::class));
        $url = 'https://sqs.us-east-1.amazonaws.com/9999/lain';

        $this->assertSame($url, $queue->getQueue($url));
    }

    public function testGetSqsHandsBackTheClient() {
        $client = m::mock(SqsClient::class);

        $this->assertSame($client, $this->makeQueue($client)->getSqs());
    }
}
