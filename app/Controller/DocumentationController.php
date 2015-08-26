<?php

namespace Htwdd\Chessapi\Controller;

use Symfony\Component\HttpFoundation\Request;

class DocumentationController
{
    public function optionsAction(Request $request)
    {
        return $request->get('_documentation');
    }
}
