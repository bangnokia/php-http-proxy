<?php

use Workerman\Connection\TcpConnection;
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;

require_once __DIR__ . '/vendor/autoload.php';

// get user and password from command line
$options = getopt('u:p:P:');
$username = $options['u'] ?? '';
$password = $options['p'] ?? '';
$port = $options['P'] ?? 6969;

// Create a TCP worker.
$worker = new Worker('tcp://0.0.0.0:' . $port);
$worker->count = 7;
$worker->name = 'php-http-proxy';

// Emitted when data received from client.
$worker->onMessage = function (TcpConnection $connection, string $buffer) use ($username, $password) {
    if ($username && $password) {
        if (strpos($buffer, 'Proxy-Authorization') !== false) {
            // find the base64 encoded string from buffer
            if (preg_match('/Proxy-Authorization: Basic (.*)\r\n/', $buffer, $matches)) {
                $auth = base64_decode($matches[1]);
                $auth = explode(':', $auth);

                if ($auth[0] !== $username || $auth[1] !== $password) {
                    $connection->close();
                    return;
                }
            } else {
                $connection->close();
                return;
            }
        } else {
            $connection->close();
            return;
        }
    }

    [$method, $address] = explode(' ', $buffer);
    $parsedUrl = parse_url($address);

    if (!isset($parsedUrl['host'])) {
        $connection->close();
        return;
    }

    $address = $parsedUrl['host'] . ':' . ($parsedUrl['port'] ?? 80);

    $remoteConnection = new AsyncTcpConnection("tcp://$address");

    $remoteConnection->onError = function($remoteConnection, $code, $msg) use ($connection) {
        $connection->send("HTTP/1.1 502 Bad Gateway\r\n\r\nConnection failed: $msg");
        $connection->close();
    };

    if ($method !== 'CONNECT') {
        $remoteConnection->send($buffer);
    } else {
        $connection->send("HTTP/1.1 200 Connection Established\r\n\r\n");
    }

    $remoteConnection->pipe($connection);
    $connection->pipe($remoteConnection);

    $remoteConnection->connect();
};

Worker::runAll();
