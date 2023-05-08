<?php

require './vendor/autoload.php';

use React\Stream\ThroughStream;
use React\Stream\CompositeStream;

$http = new React\Http\HttpServer(
    function (Psr\Http\Message\ServerRequestInterface $request) {

    if ($request->getMethod() != 'CONNECT') {
        return React\Http\Message\Response::plaintext(
            "Hello World!\n"
        );
    }
    $host = $request->getUri()->getHost();
    $port = $request->getUri()->getPort() ?: 80;

    return new React\Promise\Promise(function ($resolve, $reject) use ($host, $port) {
        
        (new React\Socket\Connector(array(
            'timeout' => 3.0,
            // 'tcp' => new Clue\React\HttpProxy\ProxyConnector('192.168.43.1:8234'), //可以做个跳板(http proxy)
            // 'tcp' => new Clue\React\Socks\Client('192.168.43.1:8235'), // 可以做个跳板(socket proxy)
            // 'dns' => false,
            
        )))->connect("tcp://$host:$port")
        ->then(function (React\Socket\ConnectionInterface $connection) use ($resolve) {
            $in = new ThroughStream;
            $out = new ThroughStream;
            
            $proxyStream = (new CompositeStream($in, $out));
            $proxyStream->pipe($connection);
            $connection->pipe($proxyStream);

            $resolve(new React\Http\Message\Response(
                React\Http\Message\Response::STATUS_OK,
                [],
                new CompositeStream(
                    $out,
                    $in
                ),
                '1.1',
                'Connection established'
            ));

        }, function (Exception $e) use ($resolve) {
            $resolve(new React\Http\Message\Response(
                React\Http\Message\Response::STATUS_BAD_GATEWAY,
                array(
                    'Content-Type' => 'text/plain'
                ),
                $e->getMessage()
            ));
        });
    });
});

$socket = new React\Socket\SocketServer('0.0.0.0:8080');
$http->listen($socket);
