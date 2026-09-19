<?php
header('Content-Type: text/plain');
$server = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['PHP_SELF']}";
$path_server = str_replace ('on_off_channels.php' , 'api.php' , $server);
$row = explode(PHP_EOL , file_get_contents("id.txt"));

echo '#EXTM3U' .PHP_EOL;
for ($i=0; $i < count($row); $i++) {
	$path = explode(";" , $row[$i]);
	$name = $path[1];
	$group = $path[2];
	$id = $path[0];
	echo '#EXTINF:-1 group-title="'.$group.'" tvg-logo="'.$path_server.'logo.png",'.ucwords(strtolower($name))." - START";
	echo PHP_EOL;
	echo $path_server."?id=".$id."&type=dtvgo_ar&action=START";
	echo PHP_EOL;
	echo '#EXTINF:-1 group-title="'.$group.'" tvg-logo="'.$path_server.'logo.png",'.ucwords(strtolower($name))." - STOP";
	echo PHP_EOL;
	echo $path_server."?id=".$id."&type=dtvgo_ar&action=STOP";
	echo PHP_EOL.PHP_EOL;
	}