<?php

require 'bootstrap.php';

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

$exchange = 'test_amqplib';
$queue = 'test_amqplib_queue_1';

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS);
$channel = $connection->channel();

/**
 * Declares exchange
 * 需服务端安装延时插件 x-delayed-message https://www.rabbitmq.com/community-plugins.html
 * CentOS下载.ez文件 https://github.com/rabbitmq/rabbitmq-delayed-message-exchange/releases/download/v3.8.0/rabbitmq_delayed_message_exchange-3.8.0.ez
 * 放置安装扩展目录下：/usr/lib/rabbitmq/lib/rabbitmq_server-3.8.9/plugins/rabbitmq_delayed_message_exchange-3.8.0.ez
 * 启用延时插件: rabbitmq-plugins enable rabbitmq_delayed_message_exchange
 * 完成  ---  到管理后台Exchange界面 查看type,存在x-delayed-message类型即可
 *查看配置文件： /etc/rabbitmq/rabbitmq.conf
 * 不存在，去github官方或网上下载配置文件，修改 端口，复制到 /etc/rabbitmq/rabbitmq.conf
 * ######## 连接监听端口
 * listeners.tcp.default = 6666
 * 重启: systemctl restart rabbitmq-server.service
 *
 * @param string $exchange
 * @param string $type
 * @param bool $passive
 * @param bool $durable
 * @param bool $auto_delete
 * @param bool $internal
 * @param bool $nowait
 * @return mixed|null
 */
$channel->exchange_declare($exchange, AMQPExchangeType::X_DELAYED_MESSAGE, false, true, false, false, false,
    new AMQPTable(["x-delayed-type" => AMQPExchangeType::FANOUT])
);
/**
 * Declares queue, creates if needed
 *
 * @param string $queue
 * @param bool $passive
 * @param bool $durable
 * @param bool $exclusive
 * @param bool $auto_delete
 * @param bool $nowait
 * @param null $arguments
 * @param null $ticket
 * @return mixed|null
 */
$channel->queue_declare($queue, false, false, false, false, false,
    new AMQPTable(["x-dead-letter-exchange" => "delayed"])
);

$channel->queue_bind($queue, $exchange);


///////////////////////////////////////////////////////////////////////////////////////////////////
/// ///////////////////////////////////////////////////////////////////////////////////////////////

$y = 0;
for ($y = 0; $y < 6; $y++) {
    //延迟秒数
    $second = pow(2, $y);
    $data = ['code' => 0, 'message' => 'ok', 'data' => ['second' => $second, 'rand' => rand()]];
    $msg = json_encode($data, JSON_UNESCAPED_UNICODE);
    $message = new AMQPMessage($msg, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
    $message->set('application_headers', new AMQPTable(["x-delay" => $second * 1000]));
    $channel->basic_publish($message, $exchange);
}


function shutdown($channel, $connection)
{
    $channel->close();
    $connection->close();
}

register_shutdown_function('shutdown', $channel, $connection);