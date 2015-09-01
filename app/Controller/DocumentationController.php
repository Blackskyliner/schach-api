<?php

namespace Htwdd\Chessapi\Controller;

use Symfony\Component\HttpFoundation\Request;

/**
 * Dieser Controller kümmert sich um alle Dokumentationsspezifischen Funktionen.
 */
class DocumentationController
{
    /**
     * Wird die API durch eine OPTIONS Methode gerufen, so wird diese Funktion aufgerufen.
     * Dabei werden die im ControllerProvider an den Request angehangenen Dokumentationsparameter
     * zurückgegeben. Da die Rückgabe dadurch ein Array ist, wird die Rückgabe durch die im ControllerProvider
     * implementierte Content-Negotiation behandelt.
     *
     * @return array
     */
    public function optionsAction(Request $request)
    {
        return $request->get('_documentation');
    }
}
