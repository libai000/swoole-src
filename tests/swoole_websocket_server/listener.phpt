--TEST--
swoole_websocket_server: addlistener
--SKIPIF--
<?php require __DIR__ . '/../include/skipif.inc'; ?>
--FILE--
<?php
require __DIR__ . '/../include/bootstrap.php';
$pm = new ProcessManager;
$pm->parentFunc = function (int $pid) use ($pm) {
    go(function () use ($pm) {
        $cli = new \Swoole\Coroutine\Http\Client('127.0.0.1', 9506);
        $cli->set(['timeout' => 5]);
        $ret = $cli->upgrade('/');
        assert($ret);
        foreach (range(1, 100) as $i)
        {
            $ret = $cli->push("hello");
            assert($ret);
            $frame = $cli->recv();
            assert($frame->data == "Swoole: hello");
        }
        $pm->kill();
    });
    swoole_event_wait();
};
$pm->childFunc = function () use ($pm) {
    $serv = new swoole_websocket_server('127.0.0.1', $pm->getFreePort(), mt_rand(0, 1) ? SWOOLE_BASE : SWOOLE_PROCESS);
    $serv->set([
        'worker_num' => 1,
        'log_file'   => '/dev/null'
    ]);

    $serv->listen('127.0.0.1', 9506, SWOOLE_SOCK_TCP);

    $serv->on('workerStart', function () use ($pm)
    {
        $pm->wakeup();
    });
    $serv->on('open', function ($swoole_server, $req)
    {
    });
    $serv->on('message', function ($swoole_server, $frame)
    {
        $swoole_server->push($frame->fd, "Swoole: {$frame->data}");
    });
    $serv->on('close', function ($swoole_server, $fd)
    {
    });
    $serv->start();
};
$pm->childFirst();
$pm->run();
?>
--EXPECT--
