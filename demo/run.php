<?php

use kkboxMusic\Api;

include_once __DIR__.'/../src/Api.php';
include_once __DIR__.'/../src/Storage.php';
include_once __DIR__.'/../vendor/autoload.php';


$id = '';
$secret = '';
$proxy = '';

$api = new Api($id, $secret, $proxy);
// $ret = $api->searchSinger('容祖儿');
$ret = $api->getSingerInfo('CkUufnD9LnwAcywq5t');
// $ret = $api->getSingerAlbums('CkUufnD9LnwAcywq5t');
// $ret = $api->getSingerTopSongs('CkUufnD9LnwAcywq5t');
// $ret = $api->getAlbumSongs('1Z6CsH0Xk0A9vdEt85');
// $ret = $api->getSongs('KpSmZuFEsGgkoPc3lq,4scNX6sjOGRFho93Eq');
// $ret = $api->getCharts();
// $ret = $api->getChartSongs('0sctvfDZM6d4BMqBSW');
// $ret = $api->getSingerCharts('CkUufnD9LnwAcywq5t');
echo json_encode($ret, JSON_UNESCAPED_UNICODE)."\n";