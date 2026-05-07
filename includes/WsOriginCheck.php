<?php

namespace MyApp;

use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response;
use Ratchet\ConnectionInterface;
use Ratchet\Http\OriginCheck;
use Psr\Http\Message\RequestInterface;

/* OriginCheck variant that allows handshakes with no Origin header. 
Those come from server-side clients (WebSocketClient.php) authed via WS_SERVER_API_KEY.
Browsers always send Origin per WS spec, so CSWSH is still covered. */
class WsOriginCheck extends OriginCheck
{
    public function onOpen(ConnectionInterface $conn, ?RequestInterface $request = null)
    {
        $originHeaders = $request !== null ? $request->getHeader('Origin') : [];

        if (empty($originHeaders)) {
            return $this->_component->onOpen($conn, $request);
        }

        $header = (string)$originHeaders[0];
        $origin = parse_url($header, PHP_URL_HOST) ?: $header;

        if (!in_array($origin, $this->allowedOrigins, true)) {
            $response = new Response(403, ['X-Powered-By' => \Ratchet\VERSION]);
            $conn->send(Message::toString($response));
            $conn->close();
            return null;
        }

        return $this->_component->onOpen($conn, $request);
    }
}