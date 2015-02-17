<?php

use GuzzleHttp\Stream\Stream;

require_once __DIR__ . '/../vendor/autoload.php';


$client = new GuzzleHttp\Client();

$request = $client->createRequest('POST', 'http://dporssi.filelib.tunk.io/puuppa/poksy');


$request->setBody(
    Stream::factory(fopen(__DIR__ . '/melanie.jpg', 'r'))
);


$res = $client->send($request);

echo $res->getStatusCode();
// "200"
echo $res->getHeader('content-type');
// 'application/json; charset=utf8'
echo $res->getBody();
// {"type":"User"...'
var_dump($res->json());
// Outputs the JSON decoded data
