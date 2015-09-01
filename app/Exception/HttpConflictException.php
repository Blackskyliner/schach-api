<?php

namespace Htwdd\Chessapi\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Diese Exception repräsentiert im System ein HTTP 409.
 * Dabei können Konfliktinformationen übergeben werden,
 * welche in der Antwort dem Konsumenten der API mittgeteilt werden.
 */
class HttpConflictException extends HttpException
{
    /**
     * @var array
     */
    protected $conflictInformation;

    /**
     * HttpConflictException constructor.
     */
    public function __construct(
        $conflictCode,
        $message,
        $property = null,
        $developerMessage = null,
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

        parent::__construct($status, $exceptionMessage, $previous, $headers, $exceptionCode);
    }

    /**
     * Ermöglicht den Zugriff auf die Konfliktinformationen.
     * Damit muss die message nicht erst umständlich geparst werden.
     *
     * @return array
     */
    public function getConflicInformation()
    {
        return $this->conflictInformation;
    }
}
