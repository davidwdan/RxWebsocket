<?php

namespace Voryx\RxWebsocket;

use Ratchet\RFC6455\Encoding\Validator;
use Ratchet\RFC6455\Handshake\Negotiator;
use React\Http\Request;
use React\Http\Response;
use Rx\Disposable\CallbackDisposable;
use Rx\Observable\AnonymousObservable;
use Rx\Observable\BaseObservable;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;
use Rx\Subject\Subject;

class Server extends BaseObservable
{
    protected $bindAddress;

    protected $port;

    /** @var Subject */
    protected $connectionSubject;

    /** @var bool */
    private $useMessageObject;

    /**
     * Server constructor.
     * @param $bindAddress
     * @param $port
     */
    public function __construct($bindAddress, $port, $useMessageObject = false)
    {
        $this->bindAddress      = $bindAddress;
        $this->port             = $port;
        $this->useMessageObject = $useMessageObject;

        $this->connectionSubject = new Subject();
    }

    private function startServer()
    {
        $socket = new \React\Socket\Server(\EventLoop\getLoop());

        $negotiator = new Negotiator(new Validator());

        $http = new \React\Http\Server($socket);
        $http->on('request', function (Request $request, Response $response) use ($negotiator) {
            $psrRequest = new \GuzzleHttp\Psr7\Request(
                $request->getMethod(),
                $request->getPath(),
                $request->getHeaders()
            );

            $negotiatorResponse = $negotiator->handshake($psrRequest);

            $response->writeHead(
                $negotiatorResponse->getStatusCode(),
                array_merge(
                    $negotiatorResponse->getHeaders(),
                    ["Content-Length" => "0"]
                )
            );

            if ($negotiatorResponse->getStatusCode() !== 101) {
                $response->end();
                return;
            }

            $connection = new MessageSubject(
                new AnonymousObservable(
                    function (ObserverInterface $observer) use ($request) {
                        $request->on('data', function ($data) use ($observer) {
                            $observer->onNext($data);
                        });
                        $request->on('error', function ($error) use ($observer) {
                            $observer->onError($error);
                        });
                        $request->on('close', function () use ($observer) {
                            $observer->onCompleted();
                        });

                        return new CallbackDisposable(
                            function () use ($request) {
                                $request->close();
                            }
                        );
                    }
                ),
                new CallbackObserver(
                    function ($x) use ($response) {
                        $response->write($x);
                    },
                    function ($error) use ($response) {
                        $response->close();
                    },
                    function () use ($response) {
                        $response->end();
                    }
                ),
                false,
                $this->useMessageObject
            );

            $this->connectionSubject->onNext($connection);
        });

        $socket->listen($this->port, $this->bindAddress);

//        $http->on('end', function () {});
//        $http->on('data', function () {});
//        $http->on('pause', function () {});
//        $http->on('resume', function () {});
    }

    public function subscribe(ObserverInterface $observer, $scheduler = null)
    {
        if (!$this->started) {
            $this->started = true;

            $this->startServer();
        }

        return $this->connectionSubject->subscribe($observer, $scheduler);
    }

    protected function doStart($scheduler)
    {

    }
}