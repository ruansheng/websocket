<?php
date_default_timezone_set('PRC');

//创建websocket服务器对象，监听0.0.0.0:9502端口
$ws = new swoole_websocket_server("0.0.0.0", 4000);

//监听WebSocket连接打开事件
$ws->on('open', function ($ws, $request) {
	echo "server: handshake success with fd{$request->fd}".PHP_EOL;
});

//监听WebSocket消息事件
$ws->on('message', function ($ws, $frame) {
	$msg =  'from'.$frame->fd.":{$frame->data}\n";
	echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}".PHP_EOL;
	$ws->push($frame->fd, "this is server");
});

//监听WebSocket连接关闭事件
$ws->on('close', function ($ws, $fd) {
	echo "client-{$fd} is closed\n";
});

$ws->start();

// /usr/local/php/bin/php -c /usr/local/php/etc/php.ini server.php