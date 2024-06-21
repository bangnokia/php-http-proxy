<?php

use Workerman\Connection\TcpConnection;
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;

require_once __DIR__ . '/vendor/autoload.php';

// get user and password from command line
$options = getopt('u:p:port:');
$username = $options['u'];
$password = $options['p'];

// Create a TCP worker.
$worker = new Worker('tcp://0.0.0.0:6969');
$worker->count = 4;
$worker->name = 'php-http-proxy';

// Emitted when data received from client.
$worker->onMessage = function (TcpConnection $connection, string $buffer) use ($username, $password) {
    if (strpos($buffer, 'Proxy-Authorization') !== false) {
        // find the base64 encoded string from buffer
        preg_match('/Proxy-Authorization: Basic (.*)\r\n/', $buffer, $matches);
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

    list($method, $addr, $http_version) = explode(' ', $buffer);
    $url_data = parse_url($addr);

    if (!isset($url_data['host'])) {
        return;
    }

    $addr = !isset($url_data['port']) ? "{$url_data['host']}:80" : "{$url_data['host']}:{$url_data['port']}";

    $remoteConnection = new AsyncTcpConnection("tcp://$addr");

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