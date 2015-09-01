<?php

namespace Htwdd\Chessapi\Controller;

use Nocarrier\Hal;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dieser Controller implementiert alle Funktionen bezüglich der Routen
 *
 *  - /
 *
 * Das Mapping, welche Funktion auf welche Route und HTTP Methode gerufen wird, geschieht
 * in der getRoutes() Funktion.
 */
class RootController
{
    /**
     * Diese Funktion beschreibt GET /
     *
     * @param Request $request
     * @return array|Hal
     */
    public function indexAction(Request $request)
    {
        if (in_array(current($request->getAcceptableContentTypes()), ['text/html', '*/*'], true)){
            return [];
        } else {
            return new Hal();
        }
    }

    /**
     * Diese Funktion beschreibt alle Route, die von diesem Controller bedient werden.
     *
     * Zugleich werde die Restriktionen und Dokumentation der einzelnen Endpunkte definiert.
     *
     * Keys und deren Bedeutung:
     *      - method: Dieser Key beschreibt einen im System registrierten Service und dessen Funktion,
     *               die beim Aufrufen der Route sich um die abarbeitung des Requests kümmert.
     *      - description: Beschreibt, was die Funktion macht.
     *      - returnValues: Welche Statuscodes werden von dieser Funktion zurückgegeben und welche,
     *                      Bedeutung haben diese im Kontext der gerufenen Funktion
     *      - content-types: Wird dieser Key angegeben, so wird die Kommunikation mit diesen Endpunkten,
     *                       auf diese Formate beschränkt. Meist wird dies bei schreibenden Methoden benötigt.
     *      - parameters: Definiert die Daten, die dieser Endpunkt erwartet.
     *      - example: Ein Beispiel für die definierten Parameter.
     *      - before: Eine Closure, welche ausgeführt werden soll bevor die definirte Methode gerufen wird.
     *      - after: Eine Closure, welche ausgeführt werden soll nachdem die definirte Methode gerufen wurde.
     *      - convert: Eine Closure, welche die übergebenen Parameter verarbeitet/konvertiert.
     *
     * @return array
     */
    public static function getRoutes()
    {
        return [
            '/' => [
                'GET' => [
                    'method' => 'controller.root:indexAction',
                    'description' => 'Schach-API Version 1.0',
                    'returnValues' => [
                        Response::HTTP_OK => 'Die Antwort enthält URIs zu den Listenansichten der verwalteten Ressourcen.'
                    ]
                ],
            ]
        ];
    }
}
