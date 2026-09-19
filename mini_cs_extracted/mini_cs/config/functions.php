<?php


function getURLBase($rURL)
{
	preg_match('/(?:[^\/]*+\/)++/', $rURL, $matches);
	return $matches[0];
}

function encryptKey($rKey)
{
	global $rAESKey;
	$method = 'AES-256-CBC';
	$key = hash('sha256', $rAESKey, true);
	$iv = openssl_random_pseudo_bytes(16);
	$ciphertext = openssl_encrypt($rKey, $method, $key, OPENSSL_RAW_DATA, $iv);
	$hash = hash_hmac('sha256', $ciphertext . $iv, $key, true);
	return $iv . $hash . $ciphertext;
}

function decryptKey($rKey)
{
	global $rAESKey;
	$method = 'AES-256-CBC';
	$iv = substr($rKey, 0, 16);
	$hash = substr($rKey, 16, 32);
	$ciphertext = substr($rKey, 48);
	$key = hash('sha256', $rAESKey, true);

	if (!hash_equals(hash_hmac('sha256', $ciphertext . $iv, $key, true), $hash)) {
		return NULL;
	}

	return openssl_decrypt($ciphertext, $method, $key, OPENSSL_RAW_DATA, $iv);
}

function plog($rText)
{
	echo '[' . date('Y-m-d h:i:s') . '] ' . $rText . "\n";
}

function getProcessCount()
{
	exec('pgrep -u mini_cs | wc -l 2>&1', $rOutput, $rRet);
	return intval($rOutput[0]);
}

function getKeyCache($rKey)
{
	if (file_exists(MAIN_DIR . 'cache/keystore/' . $rKey . '.key')) {
		return decryptkey(file_get_contents(MAIN_DIR . 'cache/keystore/' . $rKey . '.key'));
	}

	return NULL;
}

function setKeyCache($rKey, $rValue)
{
	file_put_contents(MAIN_DIR . 'cache/keystore/' . $rKey . '.key', encryptkey($rValue));

	if (file_exists(MAIN_DIR . 'cache/keystore/' . $rKey . '.key')) {
		return true;
	}

	return false;
}

function openCache($rChannel)
{
	if (file_exists(MAIN_DIR . 'cache/' . $rChannel . '.db')) {
		return json_decode(file_get_contents(MAIN_DIR . 'cache/' . $rChannel . '.db'), true);
	}

	return [];
}

function deleteCache($rChannel)
{
	if (file_exists(MAIN_DIR . 'cache/' . $rChannel . '.db')) {
		unlink(MAIN_DIR . 'cache/' . $rChannel . '.db');
	}

	return [];
}

function clearCache($rDatabase, $rID)
{
	unset($rDatabase[$rID]);
	return $rDatabase;
}

function getCache($rDatabase, $rID)
{
	if (isset($rDatabase[$rID])) {
		return $rDatabase[$rID]['value'];
	}

	return NULL;
}

function setCache($rDatabase, $rID, $rValue)
{
	global $rCacheTime;
	$rDatabase[$rID] = ['value' => $rValue, 'expires' => time() + $rCacheTime];
	return $rDatabase;
}

function saveCache($rChannel, $rDatabase)
{
	file_put_contents(MAIN_DIR . 'cache/' . $rChannel . '.db', json_encode($rDatabase));
}

function getPersistence()
{
	if (file_exists(MAIN_DIR . 'config/persistence.db')) {
		$rPersistence = json_decode(file_get_contents(MAIN_DIR . 'config/persistence.db'), true);
	}
	else {
		$rPersistence = [];
	}

	return $rPersistence;
}

function addPersistence($rScript, $rChannel)
{
	$rPersistence = getpersistence();

	if (!in_array($rChannel, $rPersistence[$rScript])) {
		$rPersistence[$rScript][] = $rChannel;
	}

	file_put_contents(MAIN_DIR . 'config/persistence.db', json_encode($rPersistence));
}

function removePersistence($rScript, $rChannel)
{
	$rPersistence = getpersistence();

	if (($rKey = array_search($rChannel, $rPersistence[$rScript])) !== false) {
		unset($rPersistence[$rScript][$rKey]);
	}

	file_put_contents(MAIN_DIR . 'config/persistence.db', json_encode($rPersistence));
}

function getKey($rType, $rData, $rMD5 = NULL)
{
	global $rChannel;

	if ($rType == 'cvattv') {
		$master_key = 'MASTER';
		return json_decode(getURL('http://127.0.0.1:18001/dazn_api/api.php?key=1&id=' . $master_key, 30), true);
	}


}

function combineSegment($rVideo, $rAudio, $rOutput)
{
	global $rFFMpeg;
	$rWait = exec($rFFMpeg . ' -hide_banner -loglevel panic -y -nostdin -i "' . $rVideo . '" -i "' . $rAudio . '" -c:v copy -c:a copy -strict experimental "' . $rOutput . '" ');
	return file_exists($rOutput);
}

function transcodeFile($rMP4File, $rTSFile)
{
	global $rFFMpeg;
	$rWait = exec($rFFMpeg . ' -hide_banner -loglevel panic -y -nostdin -i "' . $rMP4File . '" -c copy -strict experimental -bsf:v h264_mp4toannexb -f mpegts "' . $rTSFile . '" ');
	return file_exists($rTSFile);
}

function combineSegmentMP4Box($rVideo, $rAudio, $rOutput)
{
	$rWait = exec('MP4Box -add "' . $rVideo . '" -add "' . $rAudio . '" -new "' . $rOutput . '" ');
	return file_exists($rOutput);
}

function combineSegmentNos($rVideo, $rAudio, $rOutput)
{
	global $rFFMpeg;
	$rWait = exec($rFFMpeg . ' -hide_banner -loglevel panic -y -nostdin -i "' . $rVideo . '" -i "' . $rAudio . '" -c copy "' . $rOutput . '" ');
	return file_exists($rOutput);
}

function combineSegmentToPipe($rVideo, $rAudio, $rOutput)
{
	global $rFFMpeg;
	$rWait = exec($rFFMpeg . ' -hide_banner -loglevel panic -y -nostdin -i "' . $rVideo . '" -i "' . $rAudio . '" -c:v copy -c:a copy -strict experimental -f mpegts "' . $rOutput . '" 2> /dev/null');
	return file_exists($rOutput);
}

function decryptSegment($rKey, $rInput, $rOutput, $rServiceName)
{
	global $rMP4Decrypt;


	$rKeyN = explode(':', $rKey);

	switch($rServiceName){


		case "cvattv":
		$rVideoChannel = 1;
		$rAudioChannel = 1;
		break;

	}



	if (count($rKeyN) == 2) {
		
		if (strpos($rInput, 'video') !== false) {
			$rWait = exec($rMP4Decrypt . ' --key '.$rVideoChannel.':' . $rKeyN[1] . ' ' . $rInput . ' ' . $rOutput . ' 2>&1 &');
		}

		if (strpos($rInput, 'audio') !== false) {
			$rWait = exec($rMP4Decrypt . ' --key '.$rAudioChannel.':' . $rKeyN[1] . ' ' . $rInput . ' ' . $rOutput . ' 2>&1 &');
		}
	}else if (count($rKeyN) == 4){

		if (strpos($rInput, 'video') !== false) {
			$rWait = exec($rMP4Decrypt . ' --key '.$rVideoChannel.':' . $rKeyN[1] . ' ' . $rInput . ' ' . $rOutput . ' 2>&1 &');
		}

		if (strpos($rInput, 'audio') !== false) {
			$rWait = exec($rMP4Decrypt . ' --key '.$rAudioChannel.':' . $rKeyN[3] . ' ' . $rInput . ' ' . $rOutput . ' 2>&1 &');
		}

	}else {
		$rWait = exec($rMP4Decrypt . ' --key 1:' . $rKeyN[1] . ' --key 2:' . $rKey[1] . ' ' . $rInput . ' ' . $rOutput . ' 2>&1 &');
	}

	return file_exists($rOutput);
}

function clearSegments($rChannel, $rLimit = NULL)
{
	global $rMaxSegments;
	global $rVideoDir;

	if (!$rLimit) {
		$rLimit = $rMaxSegments;
	}
	$rFiles = glob($rVideoDir . '/' . $rChannel . '/final/*.mp4');
	usort($rFiles, function($a, $b) {
		return filemtime($a) - filemtime($b);
	});
	$rKeep = array_slice($rFiles, -1 * $rLimit, $rLimit, true);

	foreach ($rFiles as $rFile) {
		if (!in_array($rFile, $rKeep)) {
			unlink($rFile);
		}
	}
}

function listSegments($rChannel, $rType, $rLimit = NULL)
{
	global $rMaxSegments;
	global $rVideoDir;

	if (!$rLimit) {
		$rLimit = $rMaxSegments;
	}
	$rFiles = glob($rVideoDir . '/' . $rChannel . '/final/*.'.$rType.'.ts');
	usort($rFiles, function($a, $b) {
		return filemtime($a) - filemtime($b);
	});
	$rKeep = array_slice($rFiles, -1 * $rLimit, $rLimit, true);

	return $rKeep;
}

function clearSegmentsTS($rChannel, $rLimit = NULL)
{
	global $rMaxSegments;
	global $rVideoDir;

	if (!$rLimit) {
		$rLimit = $rMaxSegments;
	}
	$rFiles = glob($rVideoDir . '/' . $rChannel . '/final/*.ts');
	usort($rFiles, function($a, $b) {
		return filemtime($a) - filemtime($b);
	});
	$rKeep = array_slice($rFiles, -1 * $rLimit, $rLimit, true);

	foreach ($rFiles as $rFile) {
		if (!in_array($rFile, $rKeep)) {
			unlink($rFile);
		}
	}
}

function clearMD5Cache($rChannel, $rLimit = 60)
{
	global $rVideoDir;
	$rFiles = glob($rVideoDir . '/' . $rChannel . '/cache/*.md5');
	usort($rFiles, function($a, $b) {
		return filemtime($a) - filemtime($b);
	});
	$rKeep = array_slice($rFiles, -1 * $rLimit, $rLimit, true);

	foreach ($rFiles as $rFile) {
		if (!in_array($rFile, $rKeep)) {
			unlink($rFile);
		}
	}
}

function getURL($rURL, $rTimeout = 5)
{
    $rUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36';
	$rContext = stream_context_create([
		'http' => ['method' => 'GET', 'timeout' => $rTimeout, 'header' => 'User-Agent: ' . $rUA . "\r\n"],
		'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
	]);
	return file_get_contents($rURL, false, $rContext);
}

function downloadFiles($rList, $rOutput, $rUA = NULL)
{
	global $rAria;
	$rTimeout = count($rList);

	if ($rTimeout < 3) {
		$rTimeout = 12;
	}

	if (0 < count($rList)) {
		$rURLs = join("\n", $rList);
		$rTempList = MAIN_DIR . 'tmp/' . md5($rURLs) . '.txt';
		file_put_contents($rTempList, $rURLs);

		if ($rUA) {
			exec($rAria . ' -U "' . $rUA . '" --connect-timeout=3 --timeout=' . $rTimeout . ' -i "' . $rTempList . '" --dir "' . $rOutput . '" 2>&1', $rOut, $rRet);
		}
		else {
			exec($rAria . ' --connect-timeout=3 --timeout=' . $rTimeout . ' -i "' . $rTempList . '" --dir "' . $rOutput . '" 2>&1', $rOut, $rRet);
		}

		unlink($rTempList);
	}

	return true;
}

function downloadFile($rInput, $rOutput, $rPHP = false)
{
		$rUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36';
		$rOptions = [
			'http' => ['method' => 'GET', 'header' => 'User-Agent: ' . $rUA . "\r\n"],
			'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
		];
		$rContext = stream_context_create($rOptions);
	if ($rPHP) {
		file_put_contents($rOutput, file_get_contents($rInput, false, $rContext));
	}
	else {
		$rWait = exec('curl "' . $rInput . '" --output "' . $rOutput . '"');
	}
	if (file_exists($rOutput) && (0 < filesize($rOutput))) {
		return true;
	}

	return false;
}

function getCVATTVChannel($rChannel)
{
	return json_decode(getURL('http://127.0.0.1:18001/dtv_api/api.php?id='.urlencode($rChannel), 30), true);
}

function iso8601ToSeconds($input) {
  $duration = new DateInterval($input);
  $hours_to_seconds = $duration->h * 60 * 60;
  $minutes_to_seconds = $duration->i * 60;
  $seconds = $duration->s;
  return $hours_to_seconds + $minutes_to_seconds + $seconds;
}

function processSegmentsIterative($rKey, $rSegments, $rDirectory, $rUA = NULL, $rServiceName, $rLoop)
{
	$rCompleted = 23;
	$rJsonDownloadMap = $rDirectory . '/downloadMap.json';

	//If it is just starting stream, just deletes previous downloadMap.json to start populating it again
	#if($rLoop == 0)
	#{
	#	exec('rm -f ' . $rJsonDownloadMap = $rDirectory . '/downloadMap.json');
	#}

	//Creates downloadMap.json and puts empty values to populate later on each loop
	if(!is_file($rJsonDownloadMap) OR $rLoop == 0)
	{
		file_put_contents($rJsonDownloadMap, json_encode(array(''=>'')));
	}
	

	$rLoopDownload = $rLoop;

	if (!is_file($rDirectory . '/encrypted/init.audio.mp4') || (filesize($rDirectory . '/encrypted/init.audio.mp4') == 0)) {
		downloadfile($rSegments['audio'], $rDirectory . '/encrypted/init.audio.mp4', true);
	}
	if (!is_file($rDirectory . '/encrypted/init.video.mp4') || (filesize($rDirectory . '/encrypted/init.video.mp4') == 0)) {
		downloadfile($rSegments['video'], $rDirectory . '/encrypted/init.video.mp4', true);
	}

	$rDownloadPath = $rDirectory . '/aria/';

	foreach (['audio', 'video'] as $rType) {
		$rDownloads = [];
		$rDownloadMap = [];

		foreach ($rSegments['segments'] as $rSegmentID => $rSegment) {
			#$rFinalPath = $rDirectory . '/final/' . $rSegmentID . '.mp4';
			$rReadCurrentJsonMapDownload = json_decode(file_get_contents($rJsonDownloadMap), true);
			if($rType == 'video')
			{
				#plog('Segment ID: '.$rSegmentID);
			}
			$rIterativeSegment = @$rReadCurrentJsonMapDownload[$rSegmentID];
			$rFinalPath = $rDirectory . '/final/' . $rIterativeSegment . '.mp4';
			if($rType)
			{
				#plog('Final Path given Segment ID.'.$rFinalPath);
			}
			#plog('Second rLoop: '.$rLoop);

			if (!is_file($rFinalPath)) {
				$rDownloads[] = $rSegment[$rType];
				$rDownloadMap[$rSegment[$rType]] = $rSegmentID;
				#$rJsonDownloadMap = $rDirectory . '/downloadMap.json';
				if($rType == 'video')
				{
				$rReadJsonDownloadMap = json_decode(file_get_contents($rJsonDownloadMap), true);
				$rReadJsonDownloadMap[$rSegmentID] = $rLoopDownload;
				file_put_contents($rJsonDownloadMap, json_encode($rReadJsonDownloadMap));
				$rLoopDownload++;
				}
				#$rDownloadMap[$rSegment[$rType]] = $rLoop;
			}
		}

		plog('Downloading ' . count($rDownloads) . ' ' . $rType . ' segments...');
		downloadfiles($rDownloads, $rDownloadPath, $rUA);
		#print_r($rDownloads);
		foreach ($rDownloads as $rURL) {
			$rBaseName = parse_url(basename($rURL),PHP_URL_PATH);
			#plog($rBaseName);
			$rMap = $rDownloadMap[$rURL];
			$rPath = $rDownloadPath . $rBaseName;
			#plog($rPath);
			if (is_file($rPath) && (0 < filesize($rPath))) {
				#plog($rPath);
				#plog($rDirectory . '/encrypted/' . $rMap . '.' . $rType . '.m4s');
				rename($rPath, $rDirectory . '/encrypted/' . $rMap . '.' . $rType . '.m4s');
			}
		}
	}

	foreach ($rSegments['segments'] as $rSegmentID => $rSegment) {
		#$rFinalPath = $rDirectory . '/final/' . $rSegmentID . '.mp4';
		#$rFinalPath = $rDirectory . '/final/' . $rLoop . '.mp4';
		$rReadCurrentJsonMapDownload = json_decode(file_get_contents($rJsonDownloadMap), true);
		$rIterativeSegment = @$rReadCurrentJsonMapDownload[$rSegmentID];
		$rFinalPath = $rDirectory . '/final/' . $rIterativeSegment . '.mp4';
		#plog('Third rLoop: '.$rLoop);
		#plog($rFinalPath);

		if (!is_file($rFinalPath)) {
			plog('Processing segment: ' . $rSegmentID);

			if (is_file($rDirectory . '/encrypted/' . $rSegmentID . '.video.m4s')) {
				exec('cat "' . $rDirectory . '/encrypted/init.video.mp4" "' . $rDirectory . '/encrypted/' . $rSegmentID . '.video.m4s" > "' . $rDirectory . '/encrypted/' . $rSegmentID . '.video.complete.m4s"');
				$rVideoPath = $rDirectory . '/decrypted/' . $rSegmentID . '.video.mp4';

				if (!decryptsegment($rKey, $rDirectory . '/encrypted/' . $rSegmentID . '.video.complete.m4s', $rVideoPath, $rServiceName)) {
					plog('[ERROR] Failed to decrypt segment!');
				}
			}
			else {
				plog('[ERROR] Encrypted Video segment is missing!');
			}

			if (is_file($rDirectory . '/encrypted/' . $rSegmentID . '.audio.m4s')) {
				exec('cat "' . $rDirectory . '/encrypted/init.audio.mp4" "' . $rDirectory . '/encrypted/' . $rSegmentID . '.audio.m4s" > "' . $rDirectory . '/encrypted/' . $rSegmentID . '.audio.complete.m4s"');
				$rAudioPath = $rDirectory . '/decrypted/' . $rSegmentID . '.audio.mp4';

				if (is_array($rKey)) {
					$rAudioKey = end($rKey);
				}
				else {
					$rAudioKey = $rKey;
				}

				if (!decryptsegment($rAudioKey, $rDirectory . '/encrypted/' . $rSegmentID . '.audio.complete.m4s', $rAudioPath, $rServiceName)) {
					plog('[ERROR] Failed to decrypt segment!');
				}
			}
			else {
				plog('[ERROR] Encrypted Audio segment is missing!');
			}
			if (is_file($rVideoPath) && is_file($rAudioPath)) {
				plog('Combining segments...');
				combinesegment($rVideoPath, $rAudioPath, $rFinalPath);
				#plog('After combining segments rLoop: '.$rLoop);
			}
			else {
				plog('[ERROR] Segments don\'t exist to combine!');
			}

			unlink($rDirectory . '/encrypted/' . $rSegmentID . '.video.m4s');
			unlink($rDirectory . '/encrypted/' . $rSegmentID . '.audio.m4s');
			unlink($rDirectory . '/encrypted/' . $rSegmentID . '.video.complete.m4s');
			unlink($rDirectory . '/encrypted/' . $rSegmentID . '.audio.complete.m4s');
			unlink($rVideoPath);
			unlink($rAudioPath);

			if (is_file($rFinalPath)) {
				$rCompleted++;
				$rLoop++;
			}
		}
	}

	return [count($rDownloads), $rCompleted, $rLoop];
}

function updateSegments($rDirectory, $rSampleSize = 10, $rHex = true, $rSize = 43200, $rMultiplier = 1)
{
	$rFiles = glob($rDirectory . '/final/*.mp4');
	usort($rFiles, function($a, $b) {
		return filemtime($a) - filemtime($b);
	});
	$rFiles = array_slice($rFiles, -1 * $rSampleSize, $rSampleSize, true);
	$rMin = NULL;
	$rMax = NULL;

	foreach ($rFiles as $rFile) {
		if ($rHex) {
			$rInt = intval(hexdec(explode('.', basename($rFile))[0]));
		}
		else {
			$rInt = intval(explode('.', basename($rFile))[0]);
		}
		if (!$rMin || ($rInt < $rMin)) {
			$rMin = $rInt;
		}
		if (!$rMax || ($rMax < $rInt)) {
			$rMax = $rInt;
		}
	}

	if ($rMin) {
		$rOutput = '';

		foreach (range(0, $rSize) as $rAdd) {
			if ($rHex) {
				$rPath = $rDirectory . '/final/' . dechex($rMin + ($rAdd * $rMultiplier)) . '.mp4';
			}
			else {
				$rPath = $rDirectory . '/final/' . ($rMin + ($rAdd * $rMultiplier)) . '.mp4';
			}
			if (file_exists($rPath) || ($rMax < ($rMin + ($rAdd * $rMultiplier)))) {
				$rOutput .= 'file \'' . $rPath . '\'' . "\n";
			}
		}

		file_put_contents($rDirectory . '/playlist.txt', $rOutput);
	}
}

function updateSegmentsTrack($rDirectory, $rSampleSize = 10, $rHex = true, $rSize = 43200, $rMultiplier = 1, $rType)
{
	$rFiles = glob($rDirectory . '/final/*.'.$rType.'.mp4');
	usort($rFiles, function($a, $b) {
		return filemtime($a) - filemtime($b);
	});
	$rFiles = array_slice($rFiles, -1 * $rSampleSize, $rSampleSize, true);
	$rMin = NULL;
	$rMax = NULL;

	foreach ($rFiles as $rFile) {
		if ($rHex) {
			$rInt = intval(hexdec(explode('.', basename($rFile))[0]));
		}
		else {
			$rInt = intval(explode('.', basename($rFile))[0]);
		}
		if (!$rMin || ($rInt < $rMin)) {
			$rMin = $rInt;
		}
		if (!$rMax || ($rMax < $rInt)) {
			$rMax = $rInt;
		}
	}

	if ($rMin) {
		$rOutput = '';

		foreach (range(0, $rSize) as $rAdd) {
			if ($rHex) {
				$rPath = $rDirectory . '/final/' . dechex($rMin + ($rAdd * $rMultiplier)) . '.mp4';
			}
			else {
				$rPath = $rDirectory . '/final/' . ($rMin + ($rAdd * $rMultiplier)) . '.'.$rType.'.mp4';
			}
			if (file_exists($rPath) || ($rMax < ($rMin + ($rAdd * $rMultiplier)))) {
				$rOutput .= 'file \'' . $rPath . '\'' . "\n";
			}
		}

		file_put_contents($rDirectory . '/playlist.txt', $rOutput);
	}
}

function updateSegmentsRecursive($rDirectory, $rSegments, $rLoop)
{
	$rNextLoop = $rLoop + 1;
	$rOutput = '';
	foreach($rSegments as $rSegmentTime => $rSegmentUrls)
	{
		$rPath = $rDirectory . '/final/' . $rSegmentTime . '.mp4';
		$rOutput .= 'file \'' . $rPath . '\'' . "\n";
	}
	$rOutput .= 'file \'' . $rDirectory . '/playlist'.$rNextLoop.'.txt' . '\'';
	if($rLoop == 0)
	{
	file_put_contents($rDirectory . '/playlist.txt', $rOutput);
	}else{
	file_put_contents($rDirectory . '/playlist'.$rLoop.'.txt', $rOutput);
	}
}

function updateSegmentsIterative($rDirectory, $rMax)
{
	$rOutput = '';
	for($rLoop= 0; $rLoop <=$rMax; $rLoop)
	{
		$rPath = $rDirectory . '/final/' . $rLoop . '.mp4';
		$rOutput .= 'file \'' . $rPath . '\'' . "\n";
		$rLoop++;
	}
	file_put_contents($rDirectory . '/playlist.txt', trim($rOutput));
}

function updateSegmentsIterativeTrack($rDirectory, $rMax, $rType)
{
	$rOutput = '';
	for($rLoop= 0; $rLoop <=$rMax; $rLoop)
	{
		$rPath = $rDirectory . '/final/' . $rLoop . '.'.$rType.'.mp4';
		$rOutput .= 'file \'' . $rPath . '\'' . "\n";
		$rLoop++;
	}
	file_put_contents($rDirectory . '/'.$rType.'.txt', trim($rOutput));
}

function updateSegmentsRecursiveNew($rDirectory, $rSampleSize = 10, $rHex = true, $rSize = 43200, $rMultiplier = 1, $rLoop)
{
	$rNextLoop = $rLoop + 1;
	$rFiles = glob($rDirectory . '/final/*.mp4');
	usort($rFiles, function($a, $b) {
		return filemtime($a) - filemtime($b);
	});
	$rFiles = array_slice($rFiles, -1 * $rSampleSize, $rSampleSize, true);
	$rMin = NULL;
	$rMax = NULL;

	foreach ($rFiles as $rFile) {
		if ($rHex) {
			$rInt = intval(hexdec(explode('.', basename($rFile))[0]));
		}
		else {
			$rInt = intval(explode('.', basename($rFile))[0]);
		}
		if (!$rMin || ($rInt < $rMin)) {
			$rMin = $rInt;
		}
		if (!$rMax || ($rMax < $rInt)) {
			$rMax = $rInt;
		}
	}

	if ($rMin) {
		$rOutput = '';

		foreach (range(0, $rSize) as $rAdd) {
			if ($rHex) {
				$rPath = $rDirectory . '/final/' . dechex($rMin + ($rAdd * $rMultiplier)) . '.mp4';
			}
			else {
				$rPath = $rDirectory . '/final/' . ($rMin + ($rAdd * $rMultiplier)) . '.mp4';
			}
			if (file_exists($rPath) || ($rMax < ($rMin + ($rAdd * $rMultiplier)))) {
				$rOutput .= 'file \'' . $rPath . '\'' . "\n";
			}
		}
		$rOutput .= 'file \'' . $rDirectory . '/playlist'.$rNextLoop.'.txt' . '\'';

		if($rLoop == 0)
			{
				file_put_contents($rDirectory . '/playlist.txt', $rOutput);
			}else{
				file_put_contents($rDirectory . '/playlist'.$rLoop.'.txt', $rOutput);
			}
	}
}

function getServiceSegmentsDAZN($rChannelData, $rLimit = NULL, $rServiceName, $rLang)
{
	global $rMaxSegments;
	global $rMP4dump;

	if (!file_exists(MAIN_DIR . Init . $rServiceName)) {
		mkdir(MAIN_DIR . Init . $rServiceName, 493, true);
	}

	foreach (range(1, 1) as $rRetry) {
		$rUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36';
		$rOptions = [
			'http' => ['method' => 'GET', 'header' => 'User-Agent: ' . $rUA . "\r\n"],
			'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
		];
		$rContext = stream_context_create($rOptions);
		$rData = file_get_contents($rChannelData, false, $rContext);

		if (strpos($rData, '<MPD') !== false) {
			$rMPD = simplexml_load_string($rData);
			$rBaseURL = getURLBase($rChannelData);
			$rVideoStart = NULL;
			$rAudioStart = NULL;
			$rVideoTemplate = NULL;
			$rPSSH = NULL;
			$rIDs = [];

			$rStartTime = strtotime($rMPD->attributes()['availabilityStartTime']);
			$rSegmentDuration = floatval(3);
			$rDelay = floatval(str_replace('S', '', str_replace('PT', '', $rMPD->attributes()['minBufferTime'])));
			$rTime = strtotime($rMPD->attributes()['publishTime']);
			$rElapsed = floatval(iso8601ToSeconds('PT'.ceil(str_replace(array('PT','S'),'',$rMPD->attributes()['timeShiftBufferDepth'])).'S'));
			#plog($rTime);


			//find highest bandwidth
			plog('Looking for highest bandwidth available...');
			foreach($rMPD->Period->AdaptationSet as $rAdaptationSet)
			{
				if ($rAdaptationSet->attributes()['mimeType'] == 'video/mp4') {
					foreach($rAdaptationSet->Representation as $rRepresentation)
					{
						array_push($rIDs, array('bandwidth' => (string) $rRepresentation->attributes()['bandwidth'], 'id' => (string) $rRepresentation->attributes()['id']));
						#print_r($rRepresentation);
					}
				}

			}
			$keys = array_column($rIDs, 'bandwidth');
			array_multisort($keys, SORT_DESC, $rIDs);
			plog('Highest bandwidth is '. ($rIDs[0]['bandwidth']/1000)."k");
			#print_r($rIDs);

			foreach ($rMPD->Period->AdaptationSet as $rAdaptationSet) {
				if ($rAdaptationSet->attributes()['mimeType'] == 'video/mp4') {
					$rID = $rIDs[0]['id'];
					$rVideoTemplate = str_replace('$RepresentationID$', $rID, $rAdaptationSet->Representation->SegmentTemplate[0]->attributes()['media']);
					$rInitSegment = str_replace('$RepresentationID$', $rID, $rBaseURL . $rAdaptationSet->Representation->SegmentTemplate[0]->attributes()['initialization']);
					foreach($rMPD->Period->AdaptationSet[0]->ContentProtection as $rContentProtection)
					{
						if($rContentProtection->attributes()['schemeIdUri'] == 'urn:uuid:edef8ba9-79d6-4ace-a3c8-27dcd51d21ed' OR $rContentProtection->attributes()['schemeIdUri'] == 'urn:uuid:EDEF8BA9-79D6-4ACE-A3C8-27DCD51D21ED')
						{
							preg_match('/(?:\w\w\w\w)++\d\w\+[^<]++/', $rData, $matches);
							if($matches)
							{
								$rPSSH  = $matches[0];
								plog('PSSH: ' . $rPSSH);
								
							}
						}
					}
					if(!$rPSSH)
					{
						file_put_contents(MAIN_DIR . Init . $rServiceName . '/' . md5($rInitSegment), file_get_contents($rInitSegment, false, $rContext));
						$rPSSH_res = shell_exec($rMP4dump . ' --verbosity 3 --format json ' . MAIN_DIR . Init . $rServiceName . '/' . md5($rInitSegment));
						preg_match('#"data":"\\[(.+?)\\]#', $rPSSH_res, $rPSSH);
						$rPSSH = getWVBox(hex_to_base64(str_replace(' ', '', $rPSSH[1])));
						plog('PSSH: ' . $rPSSH);
					}
					break;
				}
			}

			$rObject = [
				'pssh'     => $rPSSH,
				'audio'    => NULL,
				'video'    => NULL,
				'segments' => [],
				'add'      => 1
			];

			foreach ($rMPD->Period->AdaptationSet as $rAdaptationSet) {
				if ($rAdaptationSet->attributes()['mimeType'] == 'video/mp4') {
					$rID = $rIDs[0]['id'];
					$rVideoTemplate = str_replace('$RepresentationID$', $rID, $rAdaptationSet->Representation[count($rIDs) - 1]->SegmentTemplate[0]->attributes()['media']);
					$rInitSegment = str_replace('$RepresentationID$', $rID, $rBaseURL . $rAdaptationSet->Representation[count($rIDs) - 1]->SegmentTemplate[0]->attributes()['initialization']);
					$rVideoStart = intval($rAdaptationSet->Representation->SegmentTemplate[0]->attributes()['startNumber']);
					$rCurrentNumber = $rVideoStart + ($rElapsed/$rSegmentDuration) - 2*$rLimit;
					#plog($rVideoStart);
					$rObject['video'] = $rInitSegment;

				}
			}

			$rLangAvailable = array();
			plog('Trying to find audio track for language <strong>'.strtoupper($rLang).'</strong>...');			
			//looking for audio tracks
			foreach($rMPD->Period->AdaptationSet as $rAdaptationSet){
				if(($rAdaptationSet->attributes()['mimeType'] == 'audio/mp4') OR ($rAdaptationSet->attributes()['contentType'] == 'audio')){
					array_push($rLangAvailable, $rAdaptationSet->attributes()['lang']);
				}
			}

			if(!in_array($rLang, $rLangAvailable)){
				plog('Couldn\'t find audio track');
				$rLang = $rLangAvailable[0];
				plog('Audio stream set to <strong>'.strtoupper($rLang).'</strong>');
				if(isset($rLangAvailable[1])){
					plog('Second audio track is <strong>'.strtoupper($rLangAvailable[1]).'</strong>');
				}
			}else{
				plog('Audio track found!');
			}

			foreach ($rMPD->Period->AdaptationSet as $rAdaptationSet) {
				if ($rAdaptationSet->attributes()['mimeType'] == 'audio/mp4') {
					$rID = $rAdaptationSet->Representation[0]->attributes()['id'];
					$rAudioTemplate = str_replace('$RepresentationID$', $rID, $rAdaptationSet->Representation->SegmentTemplate[0]->attributes()['media']);
					$rInitSegment = str_replace('$RepresentationID$', $rID, $rBaseURL . $rAdaptationSet->Representation->SegmentTemplate[0]->attributes()['initialization']);
					$rAudioStart = intval($rAdaptationSet->Representation->SegmentTemplate[0]->attributes()['startNumber']);
					$rObject['audio'] = $rInitSegment;

					$rRepeats = $rLimit;

					foreach (range(1, $rRepeats) as $rRepeat) {
						$rCurrentNumber += $rObject['add'];
						$rObject['segments'][$rCurrentNumber]['audio'] = str_replace('$Number$', floor($rCurrentNumber), $rBaseURL . $rAudioTemplate);
						$rObject['segments'][$rCurrentNumber]['video'] = str_replace('$Number$', floor($rCurrentNumber), $rBaseURL . $rVideoTemplate);
					}
					

					if (!$rLimit) {
						$rLimit = $rMaxSegments;
					}

						$rObject['segments'] = array_slice($rObject['segments'], -1 * $rLimit, $rLimit, true);
						return $rObject;

				}
			}
		}
	}
}

function processSegmentsIterativeNos($rKey, $rSegments, $rDirectory, $rUA = NULL, $rServiceName, $rLoop)
{
	$rCompleted = 23;
	$rJsonDownloadMap = $rDirectory . '/downloadMap.json';

	//If it is just starting stream, just deletes previous downloadMap.json to start populating it again
	#if($rLoop == 0)
	#{
	#	exec('rm -f ' . $rJsonDownloadMap = $rDirectory . '/downloadMap.json');
	#}

	//Creates downloadMap.json and puts empty values to populate later on each loop
	if(!is_file($rJsonDownloadMap) OR $rLoop == 0)
	{
		file_put_contents($rJsonDownloadMap, json_encode(array(''=>'')));
	}
	

	$rLoopDownload = $rLoop;

	if (!is_file($rDirectory . '/encrypted/init.audio.mp4') || (filesize($rDirectory . '/encrypted/init.audio.mp4') == 0)) {
		downloadfile($rSegments['audio'], $rDirectory . '/encrypted/init.audio.mp4', true);
	}
	if (!is_file($rDirectory . '/encrypted/init.video.mp4') || (filesize($rDirectory . '/encrypted/init.video.mp4') == 0)) {
		downloadfile($rSegments['video'], $rDirectory . '/encrypted/init.video.mp4', true);
	}

	$rDownloadPath = $rDirectory . '/aria/';

	foreach (['audio', 'video'] as $rType) {
		$rDownloads = [];
		$rDownloadMap = [];

		foreach ($rSegments['segments'] as $rSegmentID => $rSegment) {
			#$rFinalPath = $rDirectory . '/final/' . $rSegmentID . '.mp4';
			$rReadCurrentJsonMapDownload = json_decode(file_get_contents($rJsonDownloadMap), true);
			if($rType == 'video')
			{
				#plog('Segment ID: '.$rSegmentID);
			}
			$rIterativeSegment = @$rReadCurrentJsonMapDownload[$rSegmentID];
			$rFinalPath = $rDirectory . '/final/' . $rIterativeSegment . '.mp4';
			if($rType)
			{
				#plog('Final Path given Segment ID.'.$rFinalPath);
			}
			#plog('Second rLoop: '.$rLoop);

			if (!is_file($rFinalPath)) {
				$rDownloads[] = $rSegment[$rType];
				$rDownloadMap[$rSegment[$rType]] = $rSegmentID;
				#$rJsonDownloadMap = $rDirectory . '/downloadMap.json';
				if($rType == 'video')
				{
				$rReadJsonDownloadMap = json_decode(file_get_contents($rJsonDownloadMap), true);
				$rReadJsonDownloadMap[$rSegmentID] = $rLoopDownload;
				file_put_contents($rJsonDownloadMap, json_encode($rReadJsonDownloadMap));
				$rLoopDownload++;
				}
				#$rDownloadMap[$rSegment[$rType]] = $rLoop;
			}
		}

		plog('Downloading ' . count($rDownloads) . ' ' . $rType . ' segments...');
		downloadfiles($rDownloads, $rDownloadPath, $rUA);
		#print_r($rDownloads);
		foreach ($rDownloads as $rURL) {
			$rBaseName = parse_url(basename($rURL),PHP_URL_PATH);
			#plog($rBaseName);
			$rMap = $rDownloadMap[$rURL];
			$rPath = $rDownloadPath . $rBaseName;
			#plog($rPath);
			if (is_file($rPath) && (0 < filesize($rPath))) {
				#plog($rPath);
				#plog($rDirectory . '/encrypted/' . $rMap . '.' . $rType . '.m4s');
				rename($rPath, $rDirectory . '/encrypted/' . $rMap . '.' . $rType . '.m4s');
			}
		}
	}

	foreach ($rSegments['segments'] as $rSegmentID => $rSegment) {
		#$rFinalPath = $rDirectory . '/final/' . $rSegmentID . '.mp4';
		#$rFinalPath = $rDirectory . '/final/' . $rLoop . '.mp4';
		$rReadCurrentJsonMapDownload = json_decode(file_get_contents($rJsonDownloadMap), true);
		$rIterativeSegment = @$rReadCurrentJsonMapDownload[$rSegmentID];
		$rFinalPath = $rDirectory . '/final/' . $rIterativeSegment . '.mp4';
		#plog('Third rLoop: '.$rLoop);
		#plog($rFinalPath);

		if (!is_file($rFinalPath)) {
			plog('Processing segment: ' . $rSegmentID);

			if (is_file($rDirectory . '/encrypted/' . $rSegmentID . '.video.m4s')) {
				exec('cat "' . $rDirectory . '/encrypted/init.video.mp4" "' . $rDirectory . '/encrypted/' . $rSegmentID . '.video.m4s" > "' . $rDirectory . '/encrypted/' . $rSegmentID . '.video.complete.m4s"');
				$rVideoPath = $rDirectory . '/decrypted/' . $rSegmentID . '.video.mp4';

				if (!decryptsegment($rKey, $rDirectory . '/encrypted/' . $rSegmentID . '.video.complete.m4s', $rVideoPath, $rServiceName)) {
					plog('[ERROR] Failed to decrypt segment!');
				}
			}
			else {
				plog('[ERROR] Encrypted Video segment is missing!');
			}

			if (is_file($rDirectory . '/encrypted/' . $rSegmentID . '.audio.m4s')) {
				exec('cat "' . $rDirectory . '/encrypted/init.audio.mp4" "' . $rDirectory . '/encrypted/' . $rSegmentID . '.audio.m4s" > "' . $rDirectory . '/encrypted/' . $rSegmentID . '.audio.complete.m4s"');
				$rAudioPath = $rDirectory . '/decrypted/' . $rSegmentID . '.audio.mp4';

				if (is_array($rKey)) {
					$rAudioKey = end($rKey);
				}
				else {
					$rAudioKey = $rKey;
				}

				if (!decryptsegment($rAudioKey, $rDirectory . '/encrypted/' . $rSegmentID . '.audio.complete.m4s', $rAudioPath, $rServiceName)) {
					plog('[ERROR] Failed to decrypt segment!');
				}
			}
			else {
				plog('[ERROR] Encrypted Audio segment is missing!');
			}
			if (is_file($rVideoPath) && is_file($rAudioPath)) {
				plog('Combining segments...');
				combineSegmentNos($rVideoPath, $rAudioPath, $rFinalPath);
				#plog('After combining segments rLoop: '.$rLoop);
			}
			else {
				plog('[ERROR] Segments don\'t exist to combine!');
			}

			unlink($rDirectory . '/encrypted/' . $rSegmentID . '.video.m4s');
			unlink($rDirectory . '/encrypted/' . $rSegmentID . '.audio.m4s');
			unlink($rDirectory . '/encrypted/' . $rSegmentID . '.video.complete.m4s');
			unlink($rDirectory . '/encrypted/' . $rSegmentID . '.audio.complete.m4s');
			unlink($rVideoPath);
			unlink($rAudioPath);

			if (is_file($rFinalPath)) {
				$rCompleted++;
				$rLoop++;
			}
		}
	}

	return [count($rDownloads), $rCompleted, $rLoop];
}

function startPlaylist($rChannel)
{
	global $rFFMpeg;
	$rPlaylist = MAIN_DIR . 'video/' . $rChannel . '/playlist.txt';

	if (file_exists($rPlaylist)) {
		$rOutput = MAIN_DIR . 'hls/' . $rChannel . '/hls/playlist.m3u8';
		$rFormat = MAIN_DIR . 'hls/' . $rChannel . '/hls/segment%d.ts';

		if (!file_exists($rOutput)) {
			$rTime = time();
			$log = MAIN_DIR . 'logs/ffmpeg/' . $rChannel . '.log';
			$old_log =  MAIN_DIR . 'logs/ffmpeg/' . $rChannel . '_' . $rTime . '.log';
			  if(file_exists($log)){
			    exec('mv '. $log .' '. $old_log);
			  }
			$rPID = exec($rFFMpeg . ' -y -nostdin -hide_banner -err_detect ignore_err -nofix_dts -start_at_zero -copyts -vsync 0 -correct_ts_overflow 0 -avoid_negative_ts disabled -max_interleave_delta 0 -re -probesize 15000000 -analyzeduration 15000000 -safe 0 -f concat -i ' . $rPlaylist . ' -strict -2 -dn -acodec copy -vcodec copy -hls_flags delete_segments -hls_time 4 -hls_list_size 10 '.$rOutput.' > ' . $log . ' 2>&1 & echo $!;', $rScriptOut);
			return $rPID;
		}
	}
}

function startPlaylistDuration($rChannel, $rPlaylistSegmentDuration)
{
	global $rFFMpeg;
	$rPlaylist = MAIN_DIR . 'video/' . $rChannel . '/playlist.txt';

	if (file_exists($rPlaylist)) {
		$rOutput = MAIN_DIR . 'hls/' . $rChannel . '/hls/playlist.m3u8';
		$rFormat = MAIN_DIR . 'hls/' . $rChannel . '/hls/segment%d.ts';

		if (!file_exists($rOutput)) {
			$rTime = time();
			$log = MAIN_DIR . 'logs/ffmpeg/' . $rChannel . '.log';
			$old_log =  MAIN_DIR . 'logs/ffmpeg/' . $rChannel . '_' . $rTime . '.log';
			  if(file_exists($log)){
			    exec('mv '. $log .' '. $old_log);
			  }
			$rPID = exec($rFFMpeg . ' -y -nostdin -hide_banner -nofix_dts -start_at_zero -copyts -vsync 0 -correct_ts_overflow 0 -avoid_negative_ts disabled -max_interleave_delta 0 -re -probesize 9000000 -analyzeduration 9000000 -f concat -safe 0 -i \'' . $rPlaylist . '\' -vcodec copy -scodec copy -acodec copy -individual_header_trailer 0 -metadata service_provider="mini_cs" -f segment -segment_format mpegts -segment_time '.$rPlaylistSegmentDuration.' -segment_list_size 10 -segment_format_options mpegts_flags=+initial_discontinuity:mpegts_copyts=1 -segment_list_type m3u8 -segment_list_flags +live+delete -segment_list \'' . $rOutput . '\' \'' . $rFormat . '\' > ' . $log . ' 2>&1 & echo $!;', $rScriptOut);
			return $rPID;
		}
	}
}

function startPlaylistDurationTrack($rChannel, $rPlaylistSegmentDuration, $rType)
{
	global $rFFMpeg;
	$rPlaylist = MAIN_DIR . 'video/' . $rChannel . '/'.$rType.'.txt';

	if (file_exists($rPlaylist)) {
		$rOutput = MAIN_DIR . 'hls/' . $rChannel . '/hls/'.$rType.'.m3u8';
		$rFormat = MAIN_DIR . 'hls/' . $rChannel . '/hls/segment%d.'.$rType.'.ts';

		if (!file_exists($rOutput)) {
			$rTime = time();
			$log = MAIN_DIR . 'logs/ffmpeg/' . $rChannel . '_'.$rType.'.log';
			$old_log =  MAIN_DIR . 'logs/ffmpeg/' . $rChannel . '_'.$rType.'_' . $rTime . '.log';
			  if(file_exists($log)){
			    exec('mv '. $log .' '. $old_log);
			  }
			$rPID = exec($rFFMpeg . ' -y -nostdin -hide_banner -nofix_dts -start_at_zero -copyts -vsync 0 -correct_ts_overflow 0 -avoid_negative_ts disabled -max_interleave_delta 0 -re -probesize 9000000 -analyzeduration 9000000 -f concat -safe 0 -i \'' . $rPlaylist . '\' -vcodec copy -scodec copy -acodec copy -individual_header_trailer 0 -metadata service_provider="mini_cs" -f segment -segment_format mpegts -segment_time '.$rPlaylistSegmentDuration.' -segment_list_size 10 -segment_format_options mpegts_flags=+initial_discontinuity:mpegts_copyts=1 -segment_list_type m3u8 -segment_list_flags +live+delete -segment_list \'' . $rOutput . '\' \'' . $rFormat . '\' > ' . $log . ' 2>&1 & echo $!;', $rScriptOut);
			return $rPID;
		}
	}
}

function startPlaylistDurationTrackVideo($rChannel, $rPlaylistSegmentDuration, $rType)
{
	global $rFFMpeg;
	$rPlaylist = MAIN_DIR . 'video/' . $rChannel . '/'.$rType.'.txt';

	if (file_exists($rPlaylist)) {
		$rOutput = MAIN_DIR . 'hls/' . $rChannel . '/hls/'.$rType.'.m3u8';
		$rFormat = MAIN_DIR . 'hls/' . $rChannel . '/hls/segment%d.'.$rType.'.ts';

		if (!file_exists($rOutput)) {
			$rTime = time();
			$log = MAIN_DIR . 'logs/ffmpeg/' . $rChannel . '_'.$rType.'.log';
			$old_log =  MAIN_DIR . 'logs/ffmpeg/' . $rChannel . '_'.$rType.'_' . $rTime . '.log';
			  if(file_exists($log)){
			    exec('mv '. $log .' '. $old_log);
			  }
			$rPID = exec($rFFMpeg . ' -y -nostdin -hide_banner -nofix_dts -start_at_zero -copyts -vsync 0 -correct_ts_overflow 0 -avoid_negative_ts disabled -max_interleave_delta 0 -re -probesize 9000000 -analyzeduration 9000000 -f concat -safe 0 -i \'' . $rPlaylist . '\' -vcodec copy -individual_header_trailer 0 -metadata service_provider="mini_cs" -f segment -segment_format mpegts -segment_time '.$rPlaylistSegmentDuration.' -segment_list_size 10 -segment_format_options mpegts_flags=+initial_discontinuity:mpegts_copyts=1 -segment_list_type m3u8 -segment_list_flags +live+delete -segment_list \'' . $rOutput . '\' \'' . $rFormat . '\' > ' . $log . ' 2>&1 & echo $!;', $rScriptOut);
			return $rPID;
		}
	}
}


function startPlaylistDurationTrackAudio($rChannel, $rPlaylistSegmentDuration, $rType)
{
	global $rFFMpeg;
	$rPlaylist = MAIN_DIR . 'video/' . $rChannel . '/'.$rType.'.txt';

	if (file_exists($rPlaylist)) {
		$rOutput = MAIN_DIR . 'hls/' . $rChannel . '/hls/'.$rType.'.m3u8';
		$rFormat = MAIN_DIR . 'hls/' . $rChannel . '/hls/segment%d.'.$rType.'.ts';

		if (!file_exists($rOutput)) {
			$rTime = time();
			$log = MAIN_DIR . 'logs/ffmpeg/' . $rChannel . '_'.$rType.'.log';
			$old_log =  MAIN_DIR . 'logs/ffmpeg/' . $rChannel . '_'.$rType.'_' . $rTime . '.log';
			  if(file_exists($log)){
			    exec('mv '. $log .' '. $old_log);
			  }
			$rPID = exec($rFFMpeg . ' -y -nostdin -hide_banner -nofix_dts -start_at_zero -copyts -correct_ts_overflow 0 -avoid_negative_ts disabled -max_interleave_delta 0 -re -probesize 9000000 -analyzeduration 9000000 -f concat -safe 0 -i \'' . $rPlaylist . '\' -acodec copy -individual_header_trailer 0 -metadata service_provider="mini_cs" -f segment -segment_format mpegts -segment_time '.$rPlaylistSegmentDuration.' -segment_list_size 10 -segment_format_options mpegts_flags=+initial_discontinuity:mpegts_copyts=1 -segment_list_type m3u8 -segment_list_flags +live+delete -segment_list \'' . $rOutput . '\' \'' . $rFormat . '\' > ' . $log . ' 2>&1 & echo $!;', $rScriptOut);
			return $rPID;
		}
	}
}


function getMPDInfo($rID)
{
	$rMPDInfo = json_decode(geturl('http://cbd46b77.cdn.cms.movetv.com/cms/api/channels/' . $rID . '/schedule/now/playback_info.qvt'), true);
	$rManifestURL = $rMPDInfo['playback_info']['dash_manifest_url'];
	$rDash = geturl($rManifestURL);

	foreach ($rMPDInfo['playback_info']['clips'] as $rClip) {
		if (isset($rClip['location']) && $rClip['location'] && ($rClip['location'] !== $rQMXUrl)) {
			$rQMXData = getQMX($rClip['location']);

			if ($rQMXData['live']) {
				$rQMXUrl = $rClip['location'];
				$rPeriod = simplexml_load_string($rDash);
				return [$rPeriod, $rQMXUrl, $rQMXData];
			}
		}
	}
}

function getQMX($rURL)
{
	foreach (range(1, 1) as $rRetry) {
		$rData = json_decode(geturl($rURL), true);

		if ($rData) {
			return $rData;
		}
	}

	return NULL;
}

function getStreamInfo($rID)
{
	global $rFFProbe;
	list($rPlaylist) = array_slice(glob(MAIN_DIR . 'hls/' . $rID . '/hls/*.ts'), -1);
	$rOutput = '';

	if (file_exists($rPlaylist)) {
		exec($rFFProbe . ' -v quiet -print_format json -show_streams -show_format "' . $rPlaylist . '" 2>&1', $rOutput, $rRet);
	}

	return json_encode(json_decode(join("\n", $rOutput), true));
}

function getMissingSegments($rID, $rMax, $rLimit)
{
	$rReturn = [];

	if (0 < $rMax) {
		$rMin = ($rMax - $rLimit) + 1;

		if ($rMin <= 0) {
			$rMin = 12;
		}

		$rSegments = [];

		foreach (glob(MAIN_DIR . 'video/' . $rID . '/final/*.mp4') as $rFile) {
			$rSegments[] = intval(hexdec(explode('.', basename($rFile))[0]));
		}

		foreach (range($rMin, $rMax) as $rInt) {
			if (!in_array($rInt, $rSegments)) {
				$rReturn[] = dechex($rInt);
			}
		}
	}

	return $rReturn;
}

function updateSlingSegments($rDirectory, $rCurrentSegment, $rSampleSize = 10, $rSize = 302400)
{
	$rStart = $rCurrentSegment - $rSampleSize;

	if ($rStart <= 0) {
		$rStart = 9;
	}

	$rOutput = '';

	foreach (range($rStart, $rStart + $rSize) as $rSegmentID) {
		$rPath = $rDirectory . '/final/' . $rSegmentID . '.mp4';
		$rOutput .= 'file \'' . $rPath . '\'' . "\n";
	}

	file_put_contents($rDirectory . '/playlist.txt', $rOutput);
}

define('MAIN_DIR', '/home/mini_cs/');
define('Init', 'logs/');
require MAIN_DIR . 'config/config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(5);
$rMaxSegments = 64;
$rCacheTime = 21604;
$rDSTVLimit = 7;
$rVideoDir = MAIN_DIR . 'video';
$rHLSDir = MAIN_DIR . 'hls';
$rMP4Decrypt = MAIN_DIR . 'bin/mp4decrypt';
$rFFMpeg = MAIN_DIR . 'bin/ffmpeg';
$rFFProbe = MAIN_DIR . 'bin/ffprobe';
$rMP4dump = MAIN_DIR . 'bin/mp4dump';
$rAria = '/usr/bin/aria2c';
$days = 1;
$path = MAIN_DIR . 'cache/keystore/';
$rAESKey = '7621a37df31ee733b01761187639d7816a7fb475a425695e5449bb1cfed2e091';

if ($handle = opendir($path)) {
	while (false !== $file = readdir($handle)) {
		if (is_file($path . $file)) {
			if (filemtime($path . $file) < (time() - ($days * 24 * 60 * 60))) {
				unlink($path . $file);
			}
		}
	}
}
?>