<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Service;

use ReflectionClass;
use ReflectionException;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionObject;
use ReflectionUnionType;
use SwooleBundle\SwooleBundle\Server\Grpc\Constant;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\ContextInterface;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\StreamResponseInterface;
use SwooleBundle\SwooleBundle\Server\Grpc\Exception\ServiceException;

/**
 * Class ServiceMethodScanner
 *
 * Scans a service instance for valid gRPC methods and returns their definitions.
 */
final readonly class ServiceMethodScanner
{
    /**
     * ServiceMethodScanner constructor.
     *
     * @param object $instance The service instance to scan for gRPC methods.
     */
    public function __construct(private object $instance)
    {
    }

    /**
     * Scans the service instance for valid gRPC methods and returns their definitions.
     * todo: maybe change service method definition to be more flexible,
     * set ContextInterface as optional second parameter, so that we can support both with and without context.
     *
     * @return array<string, ServiceMethodDefinition> Array of method names to their definitions.
     * @throws ServiceException If a method does not meet gRPC requirements.
     */
    public function getMethods(): array
    {
        $reflection = new ReflectionObject($this->instance);

        $methods = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Check if its a gRPC method before doing this check
            if (count($method->getParameters()) <= 0) {
                continue;
            }

            $firstParam = $method->getParameters()[0];
            $firstParamType = $firstParam->getType();

            if ($firstParamType === null || $firstParamType->getName() !== ContextInterface::class) {
                continue;
            }

            // This is a gRPC method
            $numParameters = $method->getNumberOfParameters();
            if ($numParameters < 2 || $numParameters > 3) {
                throw new ServiceException('error method');
            }

            if ($numParameters === 2) {
                $methods[$method->getName()] = $this->parseUnaryMethod($method);
            }

            if ($numParameters !== 3) {
                continue;
            }

            $methods[$method->getName()] = $this->parseStreamMethod($method);
        }

        return $methods;
    }

    /**
     * Parses a unary gRPC method and returns its definition.
     *
     * @param ReflectionFunctionAbstract $method the method to parse
     * @return ServiceMethodDefinition the definition of the unary method
     * @throws ServiceException if the return type is a union type
     */
    private function parseUnaryMethod(ReflectionFunctionAbstract $method): ServiceMethodDefinition
    {
        [, $input] = $method->getParameters();
        $returnType = $method->getReturnType();

        if ($returnType instanceof ReflectionUnionType) {
            throw new ServiceException('error method: can\'t have union return type');
        }

        $inputType = $input->getType();
        if ($inputType === null) {
            throw new ServiceException("error method({$method->getName()}): input parameter must have a type");
        }

        if ($returnType === null) {
            throw new ServiceException("error method({$method->getName()}): method must have a return type");
        }

        return new ServiceMethodDefinition(
            name: $method->getName(),
            paramType: $inputType->getName(),
            returnType: $returnType->getName(),
            type: Constant::GRPC_CALL_TYPE_UNARY
        );
    }

    /**
     * Parses a server-streaming gRPC method and returns its definition.
     *
     * @param ReflectionFunctionAbstract $method the method to parse
     * @return ServiceMethodDefinition the definition of the stream method
     * @throws ServiceException if the return type is not void or the output parameter does not implement StreamResponseInterface
     */
    private function parseStreamMethod(ReflectionFunctionAbstract $method): ServiceMethodDefinition
    {
        [, $input, $output] = $method->getParameters();
        $returnType = $method->getReturnType();

        if ($returnType === null || $returnType->getName() !== 'void') {
            throw new ServiceException(
                "error method({$method->getName()}): since its stream response, should return void"
            );
        }

        $outputType = $output->getType();
        if ($outputType === null) {
            throw new ServiceException("error method({$method->getName()}): output parameter must have a type");
        }

        $outputClassName = $outputType->getName();

        if (!in_array(StreamResponseInterface::class, class_implements($outputClassName) ?: [], true)) {
            throw new ServiceException(
                "error method({$method->getName()}): since its stream response, the third parameter should implement " . StreamResponseInterface::class
            );
        }

        $outputReturnType = $this->retrieveSendParameterType($outputClassName);

        $inputType = $input->getType();
        if ($inputType === null) {
            throw new ServiceException("error method({$method->getName()}): input parameter must have a type");
        }

        return new ServiceMethodDefinition(
            name: $method->getName(),
            paramType: $inputType->getName(),
            returnType: $outputReturnType,
            type: Constant::GRPC_CALL_TYPE_STREAM,
            streamType: $outputClassName
        );
    }

    /**
     * Retrieves the type of the parameter accepted by the send() method of a stream response class.
     *
     * @param string $className the class name implementing StreamResponseInterface
     * @return string the type name of the send() method's parameter
     * @throws ReflectionException
     */
    private function retrieveSendParameterType(string $className): string
    {
        $rc = new ReflectionClass($className);

        $send = $rc->getMethod('send');

        [$msg] = $send->getParameters();

        $type = $msg->getType();
        if ($type === null) {
            throw new ServiceException("send() method parameter must have a type in {$className}");
        }

        return $type->getName();
    }
}
