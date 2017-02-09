<?php

require_once './jssdk.php';

$url = $_GET['url'];

# TODO: modify online
$jssdk = new JSSDK('wxbb5f8f68dfc90d6c', '5ef71993c7b0019653432c3c65bde110');
$wxconfig = $jssdk->getSignPackage($url);

header('Access-Control-Allow-Origin: *');
echo json_encode($wxconfig);
