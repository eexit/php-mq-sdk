<?php
namespace Eexit\Mq;

use Eexit\Mq\Adapter\InMemory\InMemory;
use Eexit\Mq\Adapter\InMemory\Message;

class MessageQueueTest extends \PHPUnit_Framework_TestCase
{
    public function testCannotPublishWhenNoConnection()
    {
        $this->setExpectedException(
            'LogicException',
            'Not connected! Open the connection first',
            763
        );

        $mq = new MessageQueue(new InMemory());
        $mq->publish(new Message('test_queue'));
    }

    public function testCannotListenWhenNoConnection()
    {
        $this->setExpectedException(
            'LogicException',
            'Not connected! Open the connection first',
            763
        );

        $mq = new MessageQueue(new InMemory());
        $mq->listen('test_queue', function(){});
    }

    public function testCannotAckWhenNoConnection()
    {
        $this->setExpectedException(
            'LogicException',
            'Not connected! Open the connection first',
            763
        );

        $mq = new MessageQueue(new InMemory());
        $mq->ack(new Message('test_queue'));
    }

    public function testCannotNackWhenNoConnection()
    {
        $this->setExpectedException(
            'LogicException',
            'Not connected! Open the connection first',
            763
        );

        $mq = new MessageQueue(new InMemory());
        $mq->nack(new Message('test_queue'));
    }

    public function testPublishMessages()
    {
        $adapter = new InMemory();
        $this->assertEquals(0, count($adapter));

        $mq = new MessageQueue($adapter);
        $mq->connect();

        foreach ($this->messageProvider() as $message) {
            $message = $mq->publish($message);

            $this->assertNotNull($message->getId());
            $this->assertEquals('test_queue', $message->getQueue());
        }

        $this->assertEquals(5, count($adapter));
    }

    public function testListenForMessagesInOrder()
    {
        $messageProvision = $this->messageProvider();
        $adapter = new InMemory($messageProvision);

        $this->assertEquals(5, count($adapter));

        $mq = new MessageQueue($adapter);
        $mq->connect();

        /** @var Message[] $result */
        $result = array();

        $mq->listen('test_queue', function (Message $message, MessageQueue $mq) use (&$result, $adapter) {
            static $counter = 5;
            $result[] = $message;
            $mq->ack($message);
            $counter--;
            $this->assertEquals($counter, count($adapter));
        }, array('timeout' => 2));

        $this->assertEquals(5, count($result));

        // Ensures the order is kept
        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals(
                $messageProvision[$i]->getAttribute('index'),
                $result[$i]->getAttribute('index')
            );
        }
    }

    public function testNackMessages()
    {
        $adapter = new InMemory($this->messageProvider());

        $this->assertEquals(5, count($adapter));

        $mq = new MessageQueue($adapter);
        $mq->connect();

        /** @var Message[] $result */
        $result = array();

        $mq->listen('test_queue', function (Message $message, MessageQueue $mq) use (&$result, $adapter) {
            static $failure = 0;
            static $counter = 5;

            // Nacks the third message twice
            if ($message->getAttribute('index') === 2 && $failure < 2) {
                $mq->nack($message);
                $this->assertEquals($counter, count($adapter));
                $failure++;
                return;
            }

            $result[] = $message;
            $mq->ack($message);
            $counter--;
            $this->assertEquals($counter, count($adapter));
        }, array('timeout' => 2));

        $this->assertEquals(5, count($result));

        /** @var Message $lastMessage */
        $lastMessage = array_pop($result);

        // Ensures the order of the messages: the third message should be the last one
        $this->assertEquals(2, $lastMessage->getAttribute('index'));
    }

    /**
     * @expectedException \Eexit\Mq\Adapter\AdapterException
     * @expectedExceptionMessage Connection outage
     */
    public function testFailWhenAConnectionOutageOccursWhenListeningToMessages()
    {
        $adapter = new InMemory($this->messageProvider());
        $mq = new MessageQueue($adapter);
        $mq->connect();

        $mq->listen('test_queue', function (Message $message, MessageQueue $mq) use ($adapter) {
            $mq->ack($message);

            // Breaks the connection after the third message
            if ($message->getAttribute('index') === 2) {
                $adapter->setConnected(false);
            }
        }, array('timeout' => 2));
    }

    public function testStopListeningToMessages()
    {
        $adapter = new InMemory($this->messageProvider());

        $this->assertEquals(5, count($adapter));

        $mq = new MessageQueue($adapter);
        $mq->connect();

        $this->assertTrue($adapter->isConnected());
        $this->assertFalse($adapter->isConsuming());

        $mq->listen('test_queue', function (Message $message, MessageQueue $mq) use ($adapter) {
            $mq->ack($message);
            $this->assertTrue($adapter->isConsuming());

            // Stops consuming after the fourth message
            if ($message->getAttribute('index') === 3) {
                $mq->stop();
                $this->assertFalse($adapter->isConsuming());
            }
        });

        $this->assertTrue($adapter->isConnected());
        $this->assertFalse($adapter->isConsuming());

        // Ensures one message is left in the broker
        $this->assertEquals(1, count($adapter));
    }

    public function testListeningIsStoppedBeforeClosingTheConnection()
    {
        $adapter = new InMemory($this->messageProvider());

        $mq = new MessageQueue($adapter);
        $mq->connect();

        $this->assertTrue($adapter->isConnected());
        $this->assertFalse($adapter->isConsuming());

        $mq->listen('test_queue', function (Message $message, MessageQueue $mq) use ($adapter) {
            $this->assertTrue($adapter->isConsuming());
            $mq->nack($message);
            $mq->close();
        });

        $this->assertFalse($adapter->isConsuming());
        $this->assertFalse($adapter->isConnected());
    }

    public function testFetchSpecificMessages()
    {
        /** @var \ArrayObject $messageProvision */
        $messageProvision = $this->messageProvider();

        $adapter = new InMemory($messageProvision);
        $mq = new MessageQueue($adapter);
        $mq->connect();

        /** @var Message[] $result */
        $result = array();

        $mq->listen('test_queue', function (Message $message, MessageQueue $mq) use (&$result) {
            $result[] = $message;
            $mq->ack($message);
        }, array(
            'timeout' => 2,
            'filter' => function (Message $message) {
                return $message->getAttribute('index') % 2;
            }
        ));

        $this->assertEquals(2, count($result));

        // Removes messages where (index attr % 2) == 0
        unset(
            $messageProvision[0],
            $messageProvision[2],
            $messageProvision[4]
        );
        // Re-indexes the array
        /** @var Message[] $messageProvision */
        $messageProvision = array_values($messageProvision->getArrayCopy());

        for ($i = 0; $i < 2; $i++) {
            $this->assertEquals(
                $messageProvision[$i]->getAttribute('index'),
                $result[$i]->getAttribute('index')
            );
        }
    }

    /**
     * @param int $num
     * @return \ArrayObject
     */
    private function messageProvider($num = 5)
    {
        $messages = new \ArrayObject();

        for ($i = 0; $i < $num; $i++) {
            $message = new Message('test_queue');
            $message
                ->setBody('test_body_' . $i)
                ->setAttribute('test_attr_name_' . $i, 'test_attr_value_' . $i)
                ->setAttribute('index', $i);
            $messages[] = $message;
        }

        return $messages;
    }
}
