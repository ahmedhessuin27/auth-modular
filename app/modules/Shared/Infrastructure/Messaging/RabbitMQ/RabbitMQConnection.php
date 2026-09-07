<?php

namespace App\Modules\Shared\Infrastructure\Messaging\RabbitMQ;

use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;

final class RabbitMQConnection
{
    public function connect(): AMQPSSLConnection|AMQPStreamConnection
    {
        $config = config('rabbitmq');

        if ($config['ssl']) {
            return new AMQPSSLConnection(
                $config['host'],
                $config['port'],
                $config['user'],
                $config['password'],
                $config['vhost'],
                [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'peer_name' => $config['host'],
                    'SNI_enabled' => true,
                ],
            );
        }

        return new AMQPStreamConnection(
            $config['host'],
            $config['port'],
            $config['user'],
            $config['password'],
            $config['vhost'],
        );
    }
}