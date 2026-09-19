<?php
include 'functions.php';
//set_time_limit(0);
ini_set('default_socket_timeout', 15);
ini_set('memory_limit', -1);
if (isset($argv[1]) && (strtoupper($argv[1]) == 'START')) {
	gc_enable();
	$rWait = 10000;
	$rFailWait = 15;
	$rKeyWait = 5;
	$rScript = 'cvattv';
	$rLanguage = 'NL';
	$rMaxSegmentsPlaylist = 43200;
	$rMaxSegmentsDownload = 10;
	$rPlaylistSegmentDuration = 2;
	$rLoop = 0;
	if (isset($argv[2])) {
		$rChannel = $argv[2];
		plog('Shutting down previous instances.');
		exec('kill -9 `ps -ef | grep \'DRM_' . $rChannel . '\' | grep -v grep | awk \'{print $2}\'`');
		cli_set_process_title('DRM_' . $rChannel);
		$rDatabase = deleteCache($rChannel);
		$rDatabase = setCache($rDatabase, 'php_pid', getmypid());
		saveCache($rChannel, $rDatabase);
		$rDatabase = setCache($rDatabase, 'time_start', time());
		saveCache($rChannel, $rDatabase);
		while (true) {
			plog('Starting: ' . $rChannel);
			plog('Killing directory if exists.');
			exec('rm -rf ' . MAIN_DIR . 'video/' . $rChannel);
			exec('rm -rf ' . MAIN_DIR . 'hls/' . $rChannel);
			$rDatabase = setCache($rDatabase, 'php_pid', getmypid());
			saveCache($rChannel, $rDatabase);
			plog('Creating new directory.');
			mkdir(MAIN_DIR . 'video/' . $rChannel);
			mkdir(MAIN_DIR . 'video/' . $rChannel . '/aria');
			mkdir(MAIN_DIR . 'video/' . $rChannel . '/decrypted');
			mkdir(MAIN_DIR . 'video/' . $rChannel . '/encrypted');
			mkdir(MAIN_DIR . 'video/' . $rChannel . '/final');
			mkdir(MAIN_DIR . 'hls/' . $rChannel);
			mkdir(MAIN_DIR . 'hls/' . $rChannel . '/hls');
			plog('Grabbing DASH playlist.');
			$ch_mpd = 'http://localhost:18001/dazn_api/api.php?id='.$rChannel.'&url=1';
			$ch_mpd = file_get_contents ($ch_mpd);
			$rData = ''.$ch_mpd.'';
			$rChannelData = ''.$ch_mpd.'';
      plog ($rChannelData);
			plog('Creating playlist.txt file.');
      plog ($rVideoDir . '/' . $rChannel, 14400, 'video');
      plog ($rVideoDir . '/' . $rChannel, 14400, 'audio');
			updateSegmentsIterativeTrack($rVideoDir . '/' . $rChannel, 14400, 'video');
			updateSegmentsIterativeTrack($rVideoDir . '/' . $rChannel, 14400, 'audio');

			unset($rData);

			if ($rChannelData) {
				$rStarted = false;
				$rMemoryUsage = 0;
				$rFFPID_video = NULL;
				$rFFPID_audio = NULL;
				$rStreamInfo = NULL;

				while (true) {
					plog('Start loop handler.');
					$rMemoryUsage = memory_get_usage();
					plog('Memory usage: ' . round($rMemoryUsage / 1024 / 1024, 2) . ' MB');
					plog('DRM Processes: ' . getProcessCount());
					$rKeyFail = false;
					$rStart = round(microtime(true) * 1000);

					if (!is_dir(MAIN_DIR . 'video/' . $rChannel . '/final')) {
						plog('Force stopped.');
						break;
					}

					plog('Fetching segments...');
					$rData = getURL($rChannelData);
					if (stripos($rData, '$Number') !== false){
					$rSegments = getServiceSegmentsDAZN($rChannelData, $rMaxSegmentsDownload, $rScript, $rLanguage);
					}else{
					$rSegments = getServiceSegmentsDAZNTimeNew($rChannelData, $rMaxSegmentsDownload, $rScript, $rLanguage);
					}
					if ($rSegments && (0 < strlen($rSegments['video']))) {
						$rKeys = getKeyCache(md5($rChannel));

						if (!$rKeys) {
							foreach (range(1, 3) as $rRetry) {
								plog('Get keys for ID: ' . $rChannel);
								$rData = getKey($rScript, $rSegments['pssh'], $rChannel);

								if ($rData['status']) {
									plog('Got keys: ' . $rData['key']);
									plog('Got keys!');
									$rKeys = $rData['key'];

									if (!setKeyCache(md5($rChannel), $rData['key'])) {
										plog('[FATAL] Cannot write to keystore! Exiting to conserve server integrity.');
										exit();
									}

									break;
								}
								else {
									plog('[ERROR] Failed to get key. Retry.');
								}

								unset($rRetry, $rData);
							}
						}
						else {
							plog('Key already cached.');
						}

						if ($rKeys) {
							$rCompleted = processSegmentsIterative($rKeys, $rSegments, $rVideoDir . '/' . $rChannel, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', $rScript, $rLoop);
							#plog($rLoop);
							#$rCompleted = processSegmentsIterative($rKeys, $rSegments, $rVideoDir . '/' . $rChannel, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', $rScript, $rChannel, $rLoop);
							if (0 < $rCompleted[1]) {
								plog('Finished processing segments.');

								if ($rCompleted[1] != $rCompleted[0]) {
									plog('Skip sleep period.');
									$rStart = 0;
								}
								$rLoop = $rCompleted[2];
								plog('Current number of segments downloaded: '.$rLoop);
								#plog($rLoop);
								#file_put_contents(MAIN_DIR . 'video/' . $rChannel . '/.update', '1');
								#plog('Updating segment text list.');
								#updateSegments($rVideoDir . '/' . $rChannel, 10, false, 21600, $rSegments['add']);
								#updateSegmentsIterative($rVideoDir . '/' . $rChannel, 14400);
								#updateSegments($rVideoDir . '/' . $rChannel, 10, false, 1800, $rSegments['add']);
								#$rLoop++;
								plog('Updating segment text list.');
								updateSegments($rVideoDir . '/' . $rChannel, 10, false, 14400, 1);
								#if (!$rFFPID || !file_exists('/proc/' . $rFFPID) || (file_exists(MAIN_DIR . ('hls/' . $rChannel . '/hls/playlist.m3u8')) && (60 <= time() - filemtime(MAIN_DIR . ('hls/' . $rChannel . '/hls/playlist.m3u8'))))) {
								if (!$rFFPID || !file_exists('/proc/' . $rFFPID)) {
									if (file_exists(MAIN_DIR . 'video/' . $rChannel . '/.ffmpeg')) {
										plog('[ERROR] Ffmpeg failure! Increase fail limit.');
									}

									plog('Starting Ffmpeg playlist.');
									exec('rm -f ' . MAIN_DIR . 'hls/' . $rChannel . '/hls/*');
									#file_put_contents(MAIN_DIR . 'video/' . $rChannel . '/downloadMap.json', json_encode(array(''=>'')));
									file_put_contents(MAIN_DIR . 'video/' . $rChannel . '/.ffmpeg', '1');
									//Reset loop variable to 0 to restart downloading
									#$rLoop = 0;

									if ($rFFPID) {
										exec('kill -9 ' . $rFFPID);
									}
									#updateSegmentsIterative($rVideoDir . '/' . $rChannel, 14400);
									$rFFPID = startPlaylist($rChannel);
									#$rFFPID = startPlaylistFromPipe($rChannel);
									$rDatabase = setCache($rDatabase, 'ffmpeg_pid', $rFFPID);
									$rStarted = true;
								}
								else if (!$rStreamInfo || (strlen($rStreamInfo) == 0)) {
									plog('Getting stream information.');
									$rStreamInfo = getStreamInfo($rChannel);

									if (128 < strlen($rStreamInfo)) {
										$rDatabase = setCache($rDatabase, 'stream_info', $rStreamInfo);
									}
								}

								plog('Clearing old segments.');
								clearSegments($rChannel);
								plog('Backup database to file.');
								saveCache($rChannel, $rDatabase);
								print("\n");
							}
							else {
								plog('[FATAL] No segments! Restart.');
								break;
							}
						}
						else {
							$rKeyFail = true;
						}
					}
					else {
						plog('[FATAL] Failed to fetch playlist!');
					}

					unset($rSegments, $rKeys);

					if ($rKeyFail) {
						plog('[FATAL] Failed getting key multiple times, wait a while.');
						sleep($rKeyWait);
					}
					else if (!$rStarted) {
						plog('[ERROR] Failed on first launch. Wait longer than usual.');
						sleep($rFailWait);
					}
					else {
						$rFinish = round(microtime(true) * 1000);
						$rTaken = $rFinish - $rStart;

						if ($rTaken < $rWait) {
							usleep(($rWait - $rTaken) * 1000);
						}

						unset($rFinish, $rTaken);
					}

					unset($rStart, $rKeyFail);
					sleep(3);
					gc_collect_cycles();
				}
			}
			else {
				plog('[FATAL] Failed to get DASH playlist.');
				sleep($rFailWait);
			}

			plog('Finished, cleaning up.');
			if ($rFFPID_video && file_exists('/proc/' . $rFFPID_video)) {
				exec('kill -9 ' . $rFFPID_video);
			}

			if ($rFFPID_audio && file_exists('/proc/' . $rFFPID_audio)) {
				exec('kill -9 ' . $rFFPID_audio);
			}

			unset($rMPD, $rStarted, $rFFPID_video, $rFFPID_audio, $rStreamInfo, $rMemoryUsage);
		}

		unset($rDatabase, $rWait, $rFailWait, $rKeyWait, $rChannel);
		plog('Goodbye!');
		gc_disable();
		exit();
	}
}
else if (isset($argv[1]) && (strtoupper($argv[1]) == 'STOP') && isset($argv[2])) {
	$rChannel = $argv[2];
	$rPID_video = getCache($rDatabase, 'ffmpeg_pid_video');
	$rPID_audio = getCache($rDatabase, 'ffmpeg_pid_audio');

	if ($rPID_video) {
		exec('kill -9 ' . $rPID_video);
	}

	if ($rPID_audio) {
		exec('kill -9 ' . $rPID_audio);
	}

	$rPID = getCache($rDatabase, 'php_pid');
	exec('kill -9 `ps -ef | grep \'DRM_' . $rChannel . '\' | grep -v grep | awk \'{print $2}\'`');
	exec('rm -rf ' . MAIN_DIR . 'video/' . $rChannel);
	exec('rm -rf ' . MAIN_DIR . 'hls/' . $rChannel);
	deleteCache($rChannel);
	unset($rChannel, $rPID);
	exit();
}

?>