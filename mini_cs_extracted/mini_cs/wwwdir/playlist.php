<?php
/**
*
* @ This file is created by http://DeZender.Net
* @ deZender (PHP7 Decoder for ionCube Encoder)
*
* @ Version			:	4.1.0.0
* @ Author			:	DeZender
* @ Release on		:	15.05.2020
* @ Official site	:	http://DeZender.Net
*
*/

error_reporting(0);
$rr = json_decode(file_get_contents('http://127.0.0.1:18001/index.php'), true);
$IDss = explode("\n", file_get_contents('id.txt'));
$val = [];
$id = [];
$pp = [];
$count = 0;

foreach ($IDss as $k => $IDs) {
	$xx = explode(';', $IDs);
	$val[] = $xx;
}

foreach ($rr['channels'] as $key => $r) {
	$id[] = $key;
}

$pp[] = '#EXTM3U';

foreach ($id as $key => $i) {
	foreach ($val as $v) {
		if ($v[0] == $id[$key]) {
			++$count;
			echo $v[1] . ' http://' . $_SERVER['SERVER_ADDR'] . ':18000/' . $v[0] . '/hls/playlist.m3u8';
			echo '<br>';
			$pp[] = '#EXTINF:-1, ' . $v[1];
			$pp[] = 'http://' . $_SERVER['SERVER_ADDR'] . ':18000/' . $v[0] . '/hls/playlist.m3u8';
		}
	}
}

echo 'Total: ' . $count . "\n";
$file = 'playlist.m3u';
$f = fopen($file, 'w');

foreach ($pp as $p) {
	fwrite($f, $p . "\n");
}

$reloadS = json_decode(file_get_contents('../config/persistence.db'), true);

foreach ($reloadS as $reload) {
	foreach ($reload as $rel) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:18000/' . $rel . '/hls/playlist.m3u8');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$head = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode == '404') {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:18001/api.php?id=' . $rel . '&type=sling&action=STOP');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$head = curl_exec($ch);
			curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:18001/api.php?id=' . $rel . '&type=sling&action=START');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$head = curl_exec($ch);
			$Code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			echo '<BR>reloading stream: ' . $rel;
			file_put_contents('/home/mini_cs/logs/restart.txt', date('d.m. H:i', time()) . ' ' . $rel . "\n", FILE_APPEND);
		}
	}
}

?>