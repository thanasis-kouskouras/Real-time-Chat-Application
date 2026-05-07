<?php

use MyApp\ChatController;
use MyApp\WsOriginCheck;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

require_once(__DIR__ . '/../config.php');
require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/includes/WsOriginCheck.php';

error_reporting(E_ALL & ~E_DEPRECATED);

$PORT = WS_SERVER_PORT;
$HOST = '0.0.0.0';  //Bind to all interfaces (IPv4)
$debug = in_array('--debug', $argv ?? []);

$server = IoServer::factory(
    new HttpServer(
        new WsOriginCheck(
            new WsServer(
                new ChatController($debug)
            ),
            WS_ALLOWED_ORIGINS
        )
    ),
    $PORT,
    $HOST
);

echo "WebSocket server started on port {$PORT}\n";
echo "Server-side API key authentication enabled\n";
echo "Allowed origins: " . implode(', ', WS_ALLOWED_ORIGINS) . "\n";
if ($debug) {
    echo "Debug mode enabled (verbose logging)\n";
}

$server->run();