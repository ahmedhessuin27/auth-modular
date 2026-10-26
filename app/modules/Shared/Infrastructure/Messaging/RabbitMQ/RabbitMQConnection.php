<?php

namespace App\Modules\Shared\Infrastructure\Messaging\RabbitMQ;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;

final class RabbitMQConnection
{
    public function connect(): AbstractConnection
    {
        $config = config('rabbitmq');

        $connectionConfig = new AMQPConnectionConfig();
        $connectionConfig->setHost($config['host']);
        $connectionConfig->setPort($config['port']);
        $connectionConfig->setUser($config['user']);
        $connectionConfig->setPassword($config['password']);
        $connectionConfig->setVhost($config['vhost']);
        $connectionConfig->setConnectionTimeout($config['connection_timeout']);

        if ($config['ssl']) {
            $connectionConfig->setIsSecure(true);
            $connectionConfig->setSslVerify(true);
            $connectionConfig->setSslVerifyName(true);
        }

        // Heartbeats let both sides notice a dead peer; php-amqplib needs
        // socket timeouts of at least twice the heartbeat interval.
        $heartbeat = $config['heartbeat'];
        $socketTimeout = max($heartbeat * 2, 3.0);

        $connectionConfig->setHeartbeat($heartbeat);
        $connectionConfig->setReadTimeout($socketTimeout);
        $connectionConfig->setWriteTimeout($socketTimeout);

        // Shown in the RabbitMQ management UI.
        $connectionConfig->setConnectionName(config('app.name'));

        return AMQPConnectionFactory::create($connectionConfig);
    }
}
