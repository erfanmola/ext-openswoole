--TEST--
swoole_socket_coro: readVectorAll with ssl
--SKIPIF--
<?php require __DIR__ . '/../include/skipif.inc'; ?>
--FILE--
<?php declare(strict_types = 1);
require __DIR__ . '/../include/bootstrap.php';

use Swoole\Coroutine\Socket;
use Swoole\Server;



$totalLength = 0;
$iovector = [];
$packedStr = '';

for ($i = 0; $i < 10; $i++) {
    $iovector[$i] = str_repeat(get_safe_random(1024), 128);
    $totalLength += strlen($iovector[$i]);
    $packedStr .= $iovector[$i];
}
$totalLength2 = rand(strlen($packedStr) / 2, strlen($packedStr) - 1024 * 128);

$pm = new ProcessManager;
$pm->parentFunc = function ($pid) use ($pm) {
    co::run(function () use ($pm) {
        global $totalLength, $packedStr;
        $conn = new Socket(AF_INET, SOCK_STREAM, IPPROTO_IP);
        $conn->setProtocol([
            'open_ssl' => true,
        ]);
        $conn->connect('127.0.0.1', $pm->getFreePort());

        $ret = $conn->sendAll($packedStr);
        Assert::eq($ret, $totalLength);
        $conn->recv();
        echo "DONE\n";
    });
};

$pm->childFunc = function () use ($pm) {
    co::run(function () use ($pm) {
        global $totalLength, $iovector;
        $socket = new Socket(AF_INET, SOCK_STREAM, IPPROTO_IP);
        $socket->setProtocol([
            'open_ssl' => true,
            'ssl_cert_file' => SSL_FILE_DIR . '/server.crt',
            'ssl_key_file' => SSL_FILE_DIR . '/server.key',
        ]);
        Assert::assert($socket->bind('127.0.0.1', $pm->getFreePort()));
        Assert::assert($socket->listen(MAX_CONCURRENCY));

        /** @var Socket */
        $conn = $socket->accept();
        $conn->sslHandshake();

        $iov = [];
        for ($i = 0; $i < 10; $i++) {
            $iov[] = 1024 * 128;
        }

        Assert::eq($conn->readVectorAll($iov), $iovector);
        $conn->send('close');
    });
};
$pm->childFirst();
$pm->run();
?>
--EXPECT--
DONE
