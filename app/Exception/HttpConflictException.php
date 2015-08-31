<?php

namespace Htwdd\Chessapi\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HttpConflictException extends HttpException
{
    protected $conflictInformation;
    /**
     * HttpConflictException constructor.
     */
    public function __construct(
        $conflictCode,
        $message,
        $property = null,
        $developerMessage = null,
        //$moreInfo = null,
        $status = Response::HTTP_CONFLICT,
        \Exception $previous = null,
        array $headers = array(),
        $exceptionCode = 0
    ) {
        $this->conflictInformation = [
            'status' => $status,
            'code' => $conflictCode,
            'property' => $property,
            'message' => $message,
            'developerMessage' => $developerMessage,
            //'moreInfo' => $moreInfo
        ];

        $exceptionMessage = sprintf(
            'The execution returned with %s and the error code %s.'.PHP_EOL,
            $status,
            $conflictCode
        );
        if ($property !== null) {
            $exceptionMessage .= sprintf(
                'This error is related to the property: "%s"'.PHP_EOL,
                $property
            );
        }
        $exceptionMessage .= sprintf('%s', $message).PHP_EOL.PHP_EOL;
        if ($developerMessage !== null) {
            $exceptionMessage .=  sprintf('Developer Information: %s', $developerMessage).PHP_EOL;
        }

        /*if ($moreInfo !== null) {
            $exceptionMessage .=  sprintf('More Information: %s', $moreInfo).PHP_EOL;
        }*/


        parent::__construct($status, $exceptionMessage, $previous, $headers, $exceptionCode);
    }

    public function getConflicInformation()
    {
        return $this->conflictInformation;
    }
}
