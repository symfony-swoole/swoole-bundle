<?php
// GENERATED CODE -- DO NOT EDIT!

namespace SwooleBundle\SwooleBundle\Server\Grpc\Generated;

/**
 */
class GrpcToHttpKernelRequestClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \SwooleBundle\SwooleBundle\Server\Grpc\Generated\Psr7Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\SwooleBundle\SwooleBundle\Server\Grpc\Generated\Psr7Response>
     */
    public function HandleRequest(\SwooleBundle\SwooleBundle\Server\Grpc\Generated\Psr7Request $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/SwooleBundle.GrpcToHttpKernelRequest/HandleRequest',
        $argument,
        ['\SwooleBundle\SwooleBundle\Server\Grpc\Generated\Psr7Response', 'decode'],
        $metadata, $options);
    }

}
