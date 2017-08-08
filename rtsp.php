<?php
    class rtsp_client {
        private $addr;
        private $port;
        private $user;
        private $pass;

        private $cseq = 1;
        private $auth = array();

        private $socket;

        function __construct($addr, $port, $user = false, $pass = false) {
            $this->addr = $addr;
            $this->port = $port;
            $this->user = $user;
            $this->pass = $pass;

            $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        }

        public function connect() {
            if (socket_connect($this->socket, $this->addr, $this->port)) {
                return true;
            } else {
                return false;
            }
        }

        public function disconnect() {
            socket_shutdown($socket);
            socket_close($socket);
            return true;
        }

        /*
        * public function is_connected() {
        *
        * }
        */

        public function socket_last_error() {
            return socket_last_error($this->socket);
        }

        private function digest($user, $realm, $password, $nonce, $method, $uri) {
            $ha1 = hash("md5", $user.":".$realm.":".$password);
            $ha2 = hash("md5", $method.":".$uri);
            $result = hash("md5", $ha1.":".$nonce.":".$ha2);
            return $result;
        }

        public function request($method, $uri, $options = array()) {
            if (count($this->auth) > 0) { $options['Authorization'] = $this->auth['authtype'].' username="'.$this->user.'",realm="'.$this->auth['realm'].'",nonce="'.$this->auth['nonce'].'",uri="'.$this->auth['uri'].'",response="'.$this->digest($this->user, $this->auth['realm'], $this->pass, $this->auth['nonce'], $method, $this->auth['uri']).'"'; }
            $request = $method." ".$uri." RTSP/1.0\r\n";
            $request .= "CSeq: ".$this->cseq++."\r\n";
            foreach ($options as $k => $v) {
                $request .= $k.": ".$v."\r\n";
            }
            $request .= "\r\n";
            socket_write($this->socket, $request);

            $response = '';
            while ($r = socket_read($this->socket, 2048)) {
                $response .= $r;
                if (strpos($response, "\r\n\r\n")) { break; }
            }
            $response = $this->interpret_response($response);
            if (isset($response['WWW-Authenticate'])) {
                // Did we already try to authenticate or do we not have credentials set?
                if (!$this->user || !$this->pass || isset($options['Authorization'])) { return 1; } // Authorization failed

                $wwwauth = preg_split('/(?<!,) /', $response['WWW-Authenticate']);
                if (count($wwwauth) != 2) { return 2; } // Invalid WWW-Authenticate string

                $authtype = $wwwauth[0];
                if ($authtype != 'Digest') { return 3; } // Unsupported auth method

                $authdata = array();
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
            return $response;
        }

        private function interpret_response($response) {
            $return = array();
            $lines = preg_split('/\r\n/', $response);
            foreach ($lines as $k => $v) {
                if ($k == 0) {
                    $r = preg_split('/ /', $v);
                    $return['proto'] = (isset($r[0])) ? $r[0] : '';
                    $return['code'] = (isset($r[1])) ? $r[1] : '';
                    $return['msg'] = (isset($r[2])) ? $r[2] : '';
                } else {
                    $r = preg_split('/: /', $v);
                    if (isset($r[0])) { $return[$r[0]] = (isset($r[1])) ? $r[1] : ''; }
                }
            }
            return $return;
        }

    }
?>
