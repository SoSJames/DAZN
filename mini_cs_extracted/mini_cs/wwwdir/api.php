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

include '../includes/functions.php';
$rAction = strtoupper($_GET['action']);
{
	$rChannel = $_GET['id'];
	$rScript = $_GET['type'];

	if ($rAction == 'START') {
		$rTime = time();
		addPersistence($rScript, $rChannel);
		exec(MAIN_DIR . 'php/bin/php ' . MAIN_DIR . 'includes/' . $rScript . '.php START ' . $rChannel . ' > ' . MAIN_DIR . ('logs/build/' . $rChannel . '_' . $rTime . '.log 2>&1 &'));
		echo json_encode(['status' => true]);
		exit();
	}
	else if ($rAction == 'STOP') {
		removePersistence($rScript, $rChannel);
		exec(MAIN_DIR . 'php/bin/php ' . MAIN_DIR . 'includes/' . $rScript . '.php STOP ' . $rChannel . ' > /dev/null &');
		echo json_encode(['status' => true]);
		exit();
	}
}

echo json_encode(['status' => false]);
exit();

?>