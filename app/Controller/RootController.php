<?php

namespace Htwdd\Chessapi\Controller;

use Nocarrier\Hal;
use Symfony\Component\HttpFoundation\Request;

class RootController
{
    public function indexAction(Request $request)
    {
        if (in_array(current($request->getAcceptableContentTypes()), ['text/html', '*/*'], true)){
            return [];
        } else {
            return new Hal();
        }
    }

    /**
     * Routing Setup des Controllers.
     *
     * @return array
     */
    public static function getRoutes()
    {
        return [
            '/' => [
                'GET' => [
                    'method' => 'controller.root:indexAction'
                ],
            ]
        ];
    }
}
