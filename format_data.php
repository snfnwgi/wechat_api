<?php

$content = file('data.txt');

foreach ($content as $v) {
	$info = json_decode($v, true);

	if ($info['selector'] == 2 && $info['wx_sync']['AddMsgCount'] != 0) {
		foreach ($info['wx_sync']['AddMsgList'] as $msg) {
			if ($msg['MsgType'] == 1) {
				echo $msg['Content'].PHP_EOL;
			}
		}
	}	
}
