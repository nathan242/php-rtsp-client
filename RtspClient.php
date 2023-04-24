<?php

class RtspClient
{
    protected $addr;
    protected $port;
    protected $user;
    protected $pass;

    protected $cseq = 1;
    protected $auth = [];

    protected $rawResponse;

    protected $socket;

    function __construct(string $addr, int $port, string $user = null, string $pass = null)
    {
        $this->addr = $addr;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;

        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    }

    public function connect(): bool
    {
        return socket_connect($this->socket, $this->addr, $this->port);
    }

    public function disconnect(): void
    {
        socket_shutdown($this->socket);
        socket_close($this->socket);
    }

    public function getSocketError(): int
    {
        return socket_last_error($this->socket);
    }

    public function getRawResponse(): ?string
    {
        return $this->rawResponse;
    }

    protected function digest(string $user, string $realm, string $password, string $nonce, string $method, string $uri): string
    {
        $ha1 = hash("md5", $user.":".$realm.":".$password);
        $ha2 = hash("md5", $method.":".$uri);
        $result = hash("md5", $ha1.":".$nonce.":".$ha2);

        return $result;
    }

    public function request(string $method, string $uri, array $options = []): array
    {
        if (count($this->auth) > 0) {
            $options['Authorization'] = $this->auth['authtype'].' username="'.$this->user.'",realm="'.$this->auth['realm'].'",nonce="'.$this->auth['nonce'].'",uri="'.$this->auth['uri'].'",response="'.$this->digest($this->user, $this->auth['realm'], $this->pass, $this->auth['nonce'], $method, $this->auth['uri']).'"';
        }

        $request = $method." ".$uri." RTSP/1.0\r\n";
        $request .= "CSeq: ".$this->cseq++."\r\n";
        foreach ($options as $k => $v) {
            $request .= $k.": ".$v."\r\n";
        }
        $request .= "\r\n";
        socket_write($this->socket, $request);

        $this->rawResponse = '';
        while ($r = socket_read($this->socket, 4096)) {
            $this->rawResponse .= $r;
            if (strpos($this->rawResponse, "\r\n\r\n")) { break; }
        }

        $headers = $this->interpretResponseHeaders($this->rawResponse);

        $body = '';
        if (array_key_exists('Content-Length', $headers)) {
            $body = socket_read($this->socket, $headers['Content-Length']);
            $this->rawResponse .= $body;
        }

        if (isset($headers['WWW-Authenticate'])) {
            // Did we already try to authenticate or do we not have credentials set?
            if (!$this->user || !$this->pass || isset($options['Authorization'])) {
                throw new RuntimeException('RTSP authorization failed');
            }

            $wwwauth = preg_split('/(?<!,) /', $response['WWW-Authenticate']);
            if (count($wwwauth) != 2) {
                throw new RuntimeException("Invalid WWW-Authenticate string: {$response['WWW-Authenticate']}");
            }

            $authtype = $wwwauth[0];
            if ($authtype != 'Digest') {
                throw new RuntimeException("Unsupported auth method: {$authtype}");
            }

            $authdata = [];
            $x = preg_split('/, */', $wwwauth[1]);
            foreach ($x as $a) {
                $y = preg_split('/=/', $a);
                $y[1] = preg_replace('/"/', '', $y[1]);
                $authdata[$y[0]] = (isset($y[1])) ? $y[1] : '';
            }

            $this->auth = array(
                'authtype'=>$authtype,
                'realm'=>$authdata['realm'],
                'nonce'=>$authdata['nonce'],
                'uri'=>$uri
            );

            $options['Authorization'] = $this->auth['authtype'].' username="'.$this->user.'",realm="'.$this->auth['realm'].'",nonce="'.$this->auth['nonce'].'",uri="'.$this->auth['uri'].'",response="'.$this->digest($this->user, $this->auth['realm'], $this->pass, $this->auth['nonce'], $method, $this->auth['uri']).'"';

            return $this->request($method, $uri, $options);
        }

        return [
            'headers' => $headers,
            'body' => $body
        ];
    }

    protected function interpretResponseHeaders(string $response): array
    {
        $return = [];
        $lines = preg_split('/\r\n/', $response);
        foreach ($lines as $k => $v) {
            if ($k == 0) {
                $r = preg_split('/ /', $v, 3);
                $return['proto'] = (isset($r[0])) ? $r[0] : '';
                $return['code'] = (isset($r[1])) ? $r[1] : '';
                $return['msg'] = (isset($r[2])) ? $r[2] : '';
            } else {
                $r = preg_split('/: /', $v);
                if (isset($r[1])) {
                    $return[$r[0]] = (isset($r[1])) ? $r[1] : '';
                }
            }
        }

        return $return;
    }

}

