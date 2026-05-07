<?php
/* PERSISTENT WEBSOCKET CLIENT FOR SERVER-SIDE NOTIFICATIONS

Maintains a single WebSocket connection from PHP to WebSocket server for sending notifications. */

class WebSocketClient
{
    private static $instance = null;
    private $socket = null;
    private $connected = false;
    private $host;
    private $port;
    private $apiKey;
    
    private function __construct()
    {
        
        $configHost = WS_HOST;
        $this->host = ($configHost === 'localhost') ? '127.0.0.1' : $configHost;
        $this->port = WS_SERVER_PORT;
        $this->apiKey = WS_SERVER_API_KEY;

        app_log("WebSocketClient: Initializing with host={$this->host}, port={$this->port}");

        $this->connect();
    }
    
    //Get singleton instance
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    //Connect to WebSocket server
    private function connect(): bool
    {
        if ($this->connected && $this->socket) {
            return true;
        }
        
        try {
            //Create WebSocket connection
            $this->socket = @stream_socket_client(
                "tcp://{$this->host}:{$this->port}",
                $errno,
                $errstr,
                2, //2 second timeout
                STREAM_CLIENT_CONNECT
            );
            
            if (!$this->socket) {
                app_log("WebSocket connection failed: $errstr ($errno)");
                return false;
            }
            
            //Set non-blocking mode
            stream_set_blocking($this->socket, false);
            
            //Perform WebSocket handshake
            $key = base64_encode(random_bytes(16));
            $headers = "GET /?api_key={$this->apiKey} HTTP/1.1\r\n";
            $headers .= "Host: {$this->host}:{$this->port}\r\n";
            $headers .= "Upgrade: websocket\r\n";
            $headers .= "Connection: Upgrade\r\n";
            $headers .= "Sec-WebSocket-Key: {$key}\r\n";
            $headers .= "Sec-WebSocket-Version: 13\r\n";
            $headers .= "\r\n";
            
            fwrite($this->socket, $headers);
            
            //Read handshake response (with timeout)
            $timeout = time() + 2;
            $response = '';
            while (time() < $timeout) {
                $chunk = fread($this->socket, 8192);
                if ($chunk) {
                    $response .= $chunk;
                    if (strpos($response, "\r\n\r\n") !== false) {
                        break;
                    }
                }
                usleep(10000); //10ms
            }
            
            if (strpos($response, '101 Switching Protocols') === false) {
                app_log("WebSocket handshake failed");
                app_log("Response: " . substr($response, 0, 200));
                fclose($this->socket);
                $this->socket = null;
                return false;
            }
            
            $this->connected = true;
            app_log("WebSocket server connection established");
            
            //Read the server's confirmation message (non-blocking read)
            stream_set_blocking($this->socket, false);
            $confirmTimeout = time() + 1;
            while (time() < $confirmTimeout) {
                $frame = fread($this->socket, 8192);
                if ($frame) {
                    app_log("Received server confirmation frame");
                    break;
                }
                usleep(10000); //10ms
            }
            
            //Set back to blocking for sends
            stream_set_blocking($this->socket, true);
            
            return true;
            
        } catch (Exception $e) {
            app_log("WebSocket connection error: " . $e->getMessage());
            return false;
        }
    }
    
    //Send notification through WebSocket
    public function send(array $data): bool
    {
        app_log("=== WebSocketClient::send() called ===");
        app_log("Data type: " . ($data['type'] ?? 'unknown'));
        app_log("Data keys: " . implode(', ', array_keys($data)));
        app_log("Data count: " . count($data));
        
        //Debug each field
        foreach ($data as $key => $value) {
            if (is_string($value) || is_numeric($value) || is_bool($value)) {
                app_log("  $key: " . var_export($value, true));
            } else {
                app_log("  $key: " . gettype($value));
            }
        }
        
        if (!$this->connected || !$this->socket) {
            app_log("Not connected, attempting to connect...");
            //Try to reconnect
            if (!$this->connect()) {
                app_log("ERROR: Failed to connect to WebSocket server");
                return false;
            }
            app_log("Successfully connected");
        }
        
        try {
            //Add action type for server notifications
            $data['action'] = 'server_notification';
            
            $payload = json_encode($data);
            
            if ($payload === false) {
                app_log("ERROR: json_encode failed: " . json_last_error_msg());
                return false;
            }
            
            app_log("Payload size: " . strlen($payload) . " bytes");
            app_log("Payload: " . $payload);
            
            $frame = $this->encodeFrame($payload);
            app_log("Frame size: " . strlen($frame) . " bytes");
            
            $written = @fwrite($this->socket, $frame);
            app_log("Bytes written: " . ($written !== false ? $written : 'FAILED'));
            
            if ($written === false) {
                app_log("Write failed, attempting reconnection...");
                //Connection lost, try to reconnect
                $this->connected = false;
                fclose($this->socket);
                $this->socket = null;
                
                if ($this->connect()) {
                    app_log("Reconnected, retrying send...");
                    //Retry send
                    $written = @fwrite($this->socket, $frame);
                    app_log("Retry bytes written: " . ($written !== false ? $written : 'FAILED'));
                } else {
                    app_log("ERROR: Reconnection failed");
                }
            }
            
            $success = $written !== false;
            app_log("Send result: " . ($success ? 'SUCCESS' : 'FAILED'));
            app_log("=== End WebSocketClient::send() ===\n");
            
            return $success;
            
        } catch (Exception $e) {
            app_log("WebSocket send error: " . $e->getMessage());
            $this->connected = false;
            return false;
        }
    }
    
    //Encode data as WebSocket frame
    private function encodeFrame(string $payload): string
    {
        $length = strlen($payload);
        $frame = chr(0x81); //Text frame (FIN bit set)
        
        if ($length <= 125) {
            $frame .= chr($length | 0x80); //Mask bit set
        } elseif ($length <= 65535) {
            $frame .= chr(126 | 0x80);
            $frame .= pack('n', $length);
        } else {
            $frame .= chr(127 | 0x80);
            $frame .= pack('J', $length);
        }
        
        //Masking key (required for client-to-server)
        $mask = pack('N', rand(1, 0x7FFFFFFF));
        $frame .= $mask;
        
        //Mask payload
        for ($i = 0; $i < $length; $i++) {
            $frame .= $payload[$i] ^ $mask[$i % 4];
        }
        
        return $frame;
    }
    
    //Close connection
    public function close(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
            $this->connected = false;
        }
    }
    
    //Destructor
    public function __destruct()
    {
        $this->close();
    }
}