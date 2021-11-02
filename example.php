<?php

require_once './vendor/autoload.php';

use Ternary\Http\TernaryHttp;


$url = 'http://tgoods.geniuel.com/goods/goodsDetail';
$params = ['goods_id' => '92111011168700001'];

$b = TernaryHttp::asJson()->withoutCheckResponse()->setConnectTimeout(2)
    ->setRequestTimeout(5)->post($url, $params)->json();
var_dump($b,1);



