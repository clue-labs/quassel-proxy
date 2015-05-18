<?php

use React\Socket\Server;
use React\Socket\ConnectionInterface;
use React\SocketClient\Connector;
use React\Dns\Resolver\Factory;
use Clue\Hexdump\Hexdump;
use Clue\React\Quassel\Io\PacketSplitter;
use Clue\React\Quassel\Io\Binary;
use Clue\React\Quassel\Io\Protocol;

require __DIR__ . '/vendor/autoload.php';

if (!isset($argv[1])) {
    echo 'Error: No server hostname/ip given' . PHP_EOL;
    exit(1);
}

$server = $argv[1];

$loop = React\EventLoop\Factory::create();

$resolver = new Factory();
$connector = new Connector($loop, $resolver->create('8.8.8.8', $loop));

$connect = function () use ($connector, $server) {
    return $connector->create($server, 4242);
};

$socket = new Server($loop);
$socket->listen(4242, '0.0.0.0');

$hex = new Hexdump();
$protocol = new Protocol(new Binary());

$socket->on('connection', function (ConnectionInterface $incoming) use ($connect, $hex, $protocol) {
    echo 'Connected incoming' . PHP_EOL;

    $buffered = '';

    $buffering = function ($data) use (&$buffered) {
        $buffered .= $data;

        if (isset($buffered[3]) && $buffered[3] !== "\0") {
            echo '[patched off encryption and compression]' . PHP_EOL;
            $buffered[3] = "\0";
        }
    };
    $incoming->on('data', $buffering);

    $splitter = new PacketSplitter(new Binary());
    $skip = 12;
    $incoming->on('data', function ($data) use ($hex, $splitter, &$skip, $protocol) {
        if ($skip) {
            $data = substr($data, $skip);
            $skip = 0;
        }
        $splitter->push($data, function ($data) use ($hex, $protocol) {
            echo 'Client -> Server' . PHP_EOL;
            //echo $hex->dump($data) . PHP_EOL;
            try {
                $value = $protocol->readVariant($data);
                echo json_encode($value, JSON_PRETTY_PRINT) . PHP_EOL;
                //var_dump($value);
            } catch (EException $e) {
                echo 'Can not decode: ' . $e->getMessage() . PHP_EOL;
                echo $hex->dump($data) . PHP_EOL;
            }
        });
    });

    $connect()->then(function ($outgoing) use ($incoming, $buffering, &$buffered, $hex, $protocol) {
        echo 'Connected outgoing, dumping ' . strlen($buffered) . PHP_EOL;

        $incoming->removeListener('data', $buffering);
        $outgoing->write($buffered);
        $buffered = null;

        $incoming->pipe($outgoing);
        $outgoing->pipe($incoming);

        $splitter = new PacketSplitter(new Binary());
        $skip = 4;
        $outgoing->on('data', function ($data) use ($hex, $splitter, &$skip, $protocol) {
            if ($skip) {
                $data = substr($data, $skip);
                $skip = 0;
            }
            $splitter->push($data, function ($data) use ($hex, $protocol) {
                echo 'Server -> Client' . PHP_EOL;
                //echo $hex->dump($data) . PHP_EOL;
                try {
                    echo json_encode($protocol->readVariant($data), JSON_PRETTY_PRINT) . PHP_EOL;
                } catch (EException $e) {
                    echo 'Can not decode: ' . $e->getMessage() . PHP_EOL;
                    echo $hex->dump($data) . PHP_EOL;
                }
            });
        });

        $outgoing->on('data', function ($data) use ($hex) {
            //echo 'Server -> Client' . PHP_EOL;
            //echo $hex->dump($data) . PHP_EOL;
        });
    });
});

$loop->run();
