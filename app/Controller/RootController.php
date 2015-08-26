<?php

namespace Htwdd\Chessapi\Controller;

class RootController
{
    public function indexAction()
    {
        return [];
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
