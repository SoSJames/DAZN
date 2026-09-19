<?php
error_reporting(0);
header("Content-Type: text/plain");

$canal = $_GET['id'];
$mpd_url = $_GET['url'];
$key = $_GET['key'];

if ($mpd_url==1) {
$ids = file_get_contents ("mpds/$canal.txt");
echo $ids;
}

if ($key==1) {
$pssh_keys = file_get_contents ("keys/$canal.txt");
$pssh_keys = strstr($pssh_keys, 'KEYS:');
$pssh_keys = str_replace ("KEYS:", "", $pssh_keys);
$json = array(
    'status' => "true",
    'key' => $pssh_keys);
echo json_encode($json);

}