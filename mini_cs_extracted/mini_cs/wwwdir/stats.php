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
$rLoad = sys_getloadavg();
$rCPU = floatval($rLoad[0]) / intval(shell_exec('grep -P \'^processor\' /proc/cpuinfo|wc -l'));
$rFree = explode("\n", trim(shell_exec('free')));
$rMemory = preg_split('/[\\s]+/', $rFree[1]);
$rMemUsed = intval($rMemory[2]);
$rMemTotal = intval($rMemory[1]);
$rProcesses = getProcessCount();
$rFreeSpace = disk_free_space('/home/drm/');
echo json_encode(['cpu' => $rCPU, 'memory_used' => $rMemUsed, 'memory_total' => $rMemTotal, 'drm_processes' => $rProcesses, 'freespace' => $rFreeSpace]);

?>