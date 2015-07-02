<?php
namespace Eexit\Mq\Adapter\Sqs;

class MessageTest extends \PHPUnit_Framework_TestCase
{
    public function testBlankMessageToArray()
    {
        $message = new Message(null);
        $expected = array(
            'QueueUrl' => null,
            'MessageId' => null,
            'MessageBody' => '',
            'DelaySeconds' => 0,
            'ReceiptHandle' => null,
            'MessageAttributes' => array()
        );

        $this->assertEquals($expected, $message->toArray());
    }

    public function testFullMessageToArray()
    {
        $message = new Message('test_queue');
        $message
            ->setBody('test_body')
            ->setId('test_id')
            ->setAttribute('test_attr_name', 'test_attr_value')
            ->setAttribute('ghost', 'casper')
            ->removeAttribute('ghost')
            ->setReceiptHandle('MbZj6wDWli+JvwwJaBV+3dcjk2YW2vA3+STFFljTM8tJJg6HRG6PYSasuWXPJB+CwauMZcx/KSbkJ0=');

        $expected = array(
            'QueueUrl' => 'test_queue',
            'MessageId' => 'test_id',
            'MessageBody' => 'test_body',
            'DelaySeconds' => 0,
            'ReceiptHandle' => 'MbZj6wDWli+JvwwJaBV+3dcjk2YW2vA3+STFFljTM8tJJg6HRG6PYSasuWXPJB+CwauMZcx/KSbkJ0=',
            'MessageAttributes' => array(
                'test_attr_name' => 'test_attr_value'
            )
        );

        $this->assertEquals($expected, $message->toArray());
    }

    public function testMessageDelayDoesNotAppearInMessageAttr()
    {
        $message = new Message('');
        $message->setAttribute('DelaySeconds', 2);

        $this->assertEmpty($message->getAttributes());

        $messageArray = $message->toArray();
        $this->assertEquals(2, $messageArray['DelaySeconds']);
    }

    public function testConvertToVendor()
    {
        $message = new Message('test_queue');
        $message
            ->setBody('test_body')
            ->setId('test_id')
            ->setAttribute('test_string_attr', 'value')
            ->setAttribute('test_serialize_attr', 'a:2:{s:3:"foo";s:3:"bar";i:0;b:1;}')
            ->setAttribute('test_int_attr', 128)
            ->setAttribute('test_float_attr', 128.128)
            ->setAttribute('test_bool_attr', false)
            ->setReceiptHandle('MbZj6wDWli+JvwwJaBV+3dcjk2YW2vA3+STFFljTM8tJJg6HRG6PYSasuWXPJB+CwauMZcx/KSbkJ0=');

        $expected = array(
            'QueueUrl' => 'test_queue',
            'MessageBody' => 'test_body',
            'DelaySeconds' => 0,
            'MessageAttributes' => array(
                'test_string_attr' => array(
                    'DataType' => 'String',
                    'StringValue' => 'value'
                ),
                'test_serialize_attr' => array(
                    'DataType' => 'String',
                    'StringValue' => 'a:2:{s:3:"foo";s:3:"bar";i:0;b:1;}'
                ),
                'test_int_attr' => array(
                    'DataType' => 'Number.Integer',
                    'StringValue' => '128'
                ),
                'test_float_attr' => array(
                    'DataType' => 'Number.Float',
                    'StringValue' => '128.128',
                ),
                'test_bool_attr' => array(
                    'DataType' => 'Number.Boolean',
                    'StringValue' => '0'
                )
            )
        );

        $this->assertEquals($expected, $message->toVendor());
    }

    /**
     * @return array
     */
    public function forbiddenAttributeNamesProvider()
    {
        return array(
            array('SenderId'),
            array('SentTimestamp'),
            array('ApproximateReceiveCount'),
            array('ApproximateFirstReceiveTimestamp'),
            array('MD5OfBody'),
            array('MD5OfMessageAttributes')
        );
    }

    /**
     * @dataProvider forbiddenAttributeNamesProvider
     * @expectedException \DomainException
     */
    public function testFailVendorConversionWhenForbiddenAttributeExists($name)
    {
        $message = new Message('');
        $message->setAttribute($name, 'value');
        $message->toVendor();
    }

    /**
     * @return array
     */
    public function invalidAttributeTypesProvider()
    {
        return array(
            array(new \stdClass()),
            array(array()),
            array(opendir(sys_get_temp_dir())),
            array(null),
        );
    }

    /**
     * @dataProvider invalidAttributeTypesProvider
     * @expectedException \InvalidArgumentException
     */
    public function testFailAddInvalidAttributeType($value)
    {
        $message = new Message('');
        $message->setAttribute('invalid', $value);
    }

    public function testDelayLimits()
    {
        $message = new Message('');
        $message->setAttribute('DelaySeconds', 99999999999);

        $messageArray = $message->toArray();
        $this->assertEquals(Message::MAX_DELAY, $messageArray['DelaySeconds']);

        $message->setAttribute('DelaySeconds', -1);

        $messageArray = $message->toArray();
        $this->assertEquals(0, $messageArray['DelaySeconds']);
    }

    public function testCreateFromVendor()
    {
        $payload = array(
            'QueueUrl' => 'test_queue',
            'MessageId' => 'test_id',
            'Body' => 'test_body',
            'ReceiptHandle' => 'MbZj6wDWli+JvwwJaBV+3dcjk2YW2vA3+STFFljTM8tJJg6HRG6PYSasuWXPJB+CwauMZcx/KSbkJ0=',
            'MD5OfBody' => '3a37d03e20b3b3245b460349de1e4057',
            'MD5OfMessageAttributes' => '295c5fa15a51aae6884d1d7c1d99ca50',
            'Attributes' => array(
                'SenderId' => 195004372649,
                'SentTimestamp' => 1437574314,
                'ApproximateReceiveCount' => 1,
                'ApproximateFirstReceiveTimestamp' => 1437574514
            ),
            'MessageAttributes' => array(
                'test_string_attr' => array(
                    'DataType' => 'String',
                    'StringValue' => 'value'
                ),
                'test_serialize_attr' => array(
                    'DataType' => 'String',
                    'StringValue' => 'a:2:{s:3:"foo";s:3:"bar";i:0;b:1;}'
                ),
                'test_int_attr' => array(
                    'DataType' => 'Number.Integer',
                    'StringValue' => '128'
                ),
                'test_float_attr' => array(
                    'DataType' => 'Number.Float',
                    'StringValue' => '128.128',
                ),
                'test_bool_attr' => array(
                    'DataType' => 'Number.Boolean',
                    'StringValue' => '0'
                )
            )
        );

        $expected = array(
            'QueueUrl' => 'test_queue',
            'MessageId' => 'test_id',
            'MessageBody' => 'test_body',
            'DelaySeconds' => 0,
            'ReceiptHandle' => 'MbZj6wDWli+JvwwJaBV+3dcjk2YW2vA3+STFFljTM8tJJg6HRG6PYSasuWXPJB+CwauMZcx/KSbkJ0=',
            'MessageAttributes' => array(
                'test_string_attr' => 'value',
                'test_serialize_attr' => 'a:2:{s:3:"foo";s:3:"bar";i:0;b:1;}',
                'test_int_attr' => 128,
                'test_float_attr' => 128.128,
                'test_bool_attr' => false,
                'SenderId' => 195004372649,
                'SentTimestamp' => 1437574314,
                'ApproximateReceiveCount' => 1,
                'ApproximateFirstReceiveTimestamp' => 1437574514,
                'MD5OfBody' => '3a37d03e20b3b3245b460349de1e4057',
                'MD5OfMessageAttributes' => '295c5fa15a51aae6884d1d7c1d99ca50'
            )
        );

        $message = Message::fromVendor($payload);
        $this->assertEquals($expected, $message->toArray());
        $this->assertInternalType('integer', $message->getAttribute('test_int_attr'));
        $this->assertInternalType('float', $message->getAttribute('test_float_attr'));
        $this->assertInternalType('bool', $message->getAttribute('test_bool_attr'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Array type excpected from SQS message. Given type is: object
     */
    public function testFailCreateMessageFromSomethingElseThanArray()
    {
        Message::fromVendor(new \stdClass());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Missing mandatory QueueUrl key!
     */
    public function testFailCreateMessageWhenQueueUrlIsMissing()
    {
        Message::fromVendor(array());
    }
}
