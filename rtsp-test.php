<?php
require 'RtspClient.php';
require 'SdpData.php';

$url = "rtsp://127.0.0.1:8554/";
$test = new RtspClient("127.0.0.1", 8554);

echo "connect()\n";
$test->connect();

echo "OPTIONS\n";
print_r($test->request("OPTIONS", $url));
echo "RAW: \n";
echo $test->getRawResponse();

echo "DESCRIBE\n";
$desc = $test->request("DESCRIBE", $url);
print_r($desc);
echo "RAW: \n";
$response = $test->getRawResponse();
echo $response;

$sdp = new SdpData($response);
$streams = $sdp->getMediaStreams();
echo "STREAMS: \n";
print_r($streams);

echo "SETUP\n";
$stream = $streams['video 0 RTP/AVP 96'];
$setup = $test->request("SETUP", $stream['control'], array('Transport'=>'RTP/AVP;unicast;client_port=52614-52615', 'User-Agent'=>'PHPrtsp_client/0.0.1'));
print_r($setup);
echo "RAW: \n";
echo $test->getRawResponse();

$session = explode(';', $setup['headers']['Session'])[0];

echo "PLAY\n";
print_r($test->request("PLAY", $url, array('Session' => $session, 'User-Agent'=>'PHPrtsp_client/0.0.1')));
echo "RAW: \n";
echo $test->getRawResponse();

echo "GET_PARAMETER\n";
print_r($test->request("GET_PARAMETER", $url, array('Session' => $session)));
echo "RAW: \n";
echo $test->getRawResponse();

echo "sleep(30)\n";
sleep(30);
?>
