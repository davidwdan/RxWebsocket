<?php


namespace Rx\Websocket\RFC6455\Handshake;


use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class ClientNegotiator implements ClientNegotiatorInterface {
    public $defaultHeaders = [
        'Connection'            => 'Upgrade'
        , 'Cache-Control'         => 'no-cache'
        , 'Pragma'                => 'no-cache'
        , 'Upgrade'               => 'websocket'
        , 'Sec-WebSocket-Version' => 13
        , 'User-Agent'            => "RxPHPWebsocket/0.0.0"
    ];

    /** @var  Request */
    public $request;

    /** @var  Response */
    public $response;

    /** @var  ResponseVerifier */
    public $verifier;

    private $websocketKey = '';

    /** @var array */
    private $subProtocols;

    function __construct($path = "ws://127.0.0.1:9001/", array $subProtocols = [])
    {
        $request = new Request("GET", $path);

        $this->request = $request;

        $this->verifier = new ResponseVerifier();

        $this->websocketKey = $this->generateKey();
        $this->subProtocols = $subProtocols;
    }

    public function addRequiredHeaders() {
        foreach ($this->defaultHeaders as $k => $v) {
            // remove any header that is there now
            $this->request = $this->request->withoutHeader($k);
            $this->request = $this->request->withHeader($k, $v);
        }
        if (!empty($this->subProtocols)) {
            $this->request = $this->request->withoutHeader('Sec-WebSocket-Protocol');
            $this->request = $this->request->withHeader('Sec-WebSocket-Protocol', $this->subProtocols);
        }
        $this->request = $this->request->withoutHeader("Sec-WebSocket-Key");
        $this->request = $this->request->withHeader("Sec-WebSocket-Key", $this->websocketKey);
    }

    public function getRequest() {
        $this->addRequiredHeaders();
        return $this->request;
    }

    public function getResponse() {
        return $this->response;
    }

    public function validateResponse(Response $response) {
        $this->response = $response;

        return $this->verifier->verifyAll($this->getRequest(), $response);
    }

    protected function generateKey() {
        $chars     = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwzyz1234567890+/=';
        $charRange = strlen($chars) - 1;
        $key       = '';
        for ($i = 0;$i < 16;$i++) {
            $key .= $chars[mt_rand(0, $charRange)];
        }
        return base64_encode($key);
    }

} 