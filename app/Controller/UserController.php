<?php

namespace Htwdd\Chessapi\Controller;

use Htwdd\Chessapi\DataTransformer\UserTransformer;
use Htwdd\Chessapi\Entity\User;
use Htwdd\Chessapi\Entity\UserManager;
use Htwdd\Chessapi\Exception\HttpConflictException;
use Htwdd\Chessapi\UrlGeneratorAwareInterface;
use Htwdd\Chessapi\UrlGeneratorAwareTrait;
use Nocarrier\Hal;
use Nocarrier\HalLink;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Dieser Controller implementiert alle Funktionen bezüglich der Routen.
 *
 *  - /users/
 *  - /users/{id}
 *
 * Das Mapping, welche Funktion auf welche Route und HTTP Methode gerufen wird, geschieht
 * in der getRoutes() Funktion.
 */
class UserController implements UrlGeneratorAwareInterface
{
    /** @var  UserManager */
    private $userManager;

    /* Importiere standard Getter/Setter für das RouterAwareInterface */
    use UrlGeneratorAwareTrait;

    /**
     * UserController constructor.
     *
     * @param UserManager $userManager
     */
    public function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    public static function authenticateUser(){

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
                    'method' => 'controller.user:listAction',
                    'description' => 'Gibt eine Liste von URIs aller Spieler zurück. Dabei kann der ' .
                        'GET Parameter embed verwendet werden, ' .
                        'um die jeweilige Spieler in die Antwort direkt mit einzubinden.',
                    'returnValues' => [
                        Response::HTTP_OK => 'Die Antwort enthält eine Liste von URIs aller Spieler.',
                    ],
                ],
                'POST' => [
                    'method' => 'controller.user:createAction',
                    'description' => 'Erstellt einen Spieler.',
                    'content-types' => [
                        'application/x-www-form-urlencoded',
                        'application/json',
                        'text/xml',
                    ],
                    'parameters' => [
                        'name' => [
                            'required' => true,
                            'type' => 'text/plain',
                            'description' => 'Name des Spielers',
                        ],
                        'password' => [
                            'required' => true,
                            'type' => 'text/plain',
                            'description' => 'Passwort des Spielers',
                        ],
                    ],
                    'example' => [
                        'name' => 'Bernd',
                        'password' => '5e(R37',
                    ],
                    'returnValues' => [
                        Response::HTTP_CREATED => 'Der Spieler wurde erfolgreich erstellt und ' .
                            'die aktuelle Ressourcenrepräsentation ist in der Antwort enthalten.',
                        Response::HTTP_CONFLICT => 'Der aus den Parametern resultierende Spieler ist ungültig.',
                        Response::HTTP_INTERNAL_SERVER_ERROR => 'Der Spieler konnte nicht auf dem Server gespeichert werden.',
                    ],
                ],
            ],
            '/{id}' => [
                'GET' => [
                    'method' => 'controller.user:detailAction',
                    'description' => 'Gibt die Detailes eine Spielers zurück.',
                    'returnValues' => [
                        Response::HTTP_OK => 'Der Spieler wurde gefunden befindet sich in der Antwort.',
                        Response::HTTP_NOT_FOUND => 'Der Spieler wurde nicht gefunden.',
                    ],
                ],
                'PUT' => [
                    'method' => 'controller.user:replaceAction',
                    'content-types' => [
                        'application/x-www-form-urlencoded',
                        'application/json',
                        'text/xml',
                    ],
                    'description' => 'Überschreibt oder erstellt einen Spieler mit den angegebenen Parametern.',
                    'parameters' => [
                        'name' => [
                            'required' => true,
                            'type' => 'text/plain',
                            'description' => 'Name des Spielers',
                        ],
                        'password' => [
                            'required' => true,
                            'type' => 'text/plain',
                            'description' => 'Passwort des Spielers',
                        ],
                    ],
                    'before' => ['Htwdd\Chessapi\Service\UserAuthenticator', 'beforeControllerAction'],
                    'example' => [
                        'name' => 'Bernd',
                        'password' => '5e(R37',
                    ],
                    'returnValues' => [
                        Response::HTTP_OK => 'Der Spieler wurde erfolgreich ersetzt und ' .
                            'die aktuelle Ressourcenrepräsentation ist in der Antwort enthalten.',
                        Response::HTTP_CREATED => 'Der Spieler wurde erfolgreich erstellt und ' .
                            'die aktuelle Ressourcenrepräsentation ist in der Antwort enthalten.',
                        Response::HTTP_CONFLICT => 'Der aus den Parametern resultierende Spieler ist ungültig.',
                        Response::HTTP_INTERNAL_SERVER_ERROR => 'Der Spieler konnte nicht auf dem Server gespeichert werden.',
                        Response::HTTP_UNAUTHORIZED => 'Der Spieler kann nur von sich bearbeitet gelöscht werden, entsprechend muss dieser sich vorher authentifizieren.',
                        Response::HTTP_FORBIDDEN => 'Es wurden falsche Basic Auth Daten angegeben. Ein Zugriff wurde verweigert.',
                    ],
                ],
                'POST' => [
                    'method' => 'controller.user:updateAction',
                    'content-types' => [
                        'application/x-www-form-urlencoded',
                        'application/json',
                        'text/xml',
                    ],
                    'description' => 'Aktualisiert den Spieler anhand der übergebenen Parameter.',
                    'parameters' => [
                        'name' => [
                            'required' => false,
                            'type' => 'text/plain',
                            'description' => 'Name des Spielers',
                        ],
                        'password' => [
                            'required' => false,
                            'type' => 'text/plain',
                            'description' => 'Passwort des Spielers',
                        ],
                    ],
                    'before' => ['Htwdd\Chessapi\Service\UserAuthenticator', 'beforeControllerAction'],
                    'example' => [
                        'name' => 'Bernd',
                        'password' => '5e(R37',
                    ],
                    'returnValues' => [
                        Response::HTTP_OK => 'Der Spieler wurde erfolgreich aktualisiert und ' .
                            'die aktuelle Ressourcenrepräsentation ist in der Antwort enthalten.',
                        Response::HTTP_NOT_FOUND => 'Der Spieler wurde nicht gefunden.',
                        Response::HTTP_CONFLICT => 'Der aus den Parametern resultierende Spieler ist ungültig.',
                        Response::HTTP_INTERNAL_SERVER_ERROR => 'Der Spieler konnte nicht auf dem Server gespeichert werden.',
                        Response::HTTP_UNAUTHORIZED => 'Der Spieler kann nur von sich selbst bearbeitet werden, entsprechend muss dieser sich vorher authentifizieren.',
                        Response::HTTP_FORBIDDEN => 'Es wurden falsche Basic Auth Daten angegeben. Ein Zugriff wurde verweigert.',
                    ],
                ],
                'DELETE' => [
                    'method' => 'controller.user:deleteAction',
                    'description' => 'Löscht einen Spieler',
                    'before' => ['Htwdd\Chessapi\Service\UserAuthenticator', 'beforeControllerAction'],
                    'returnValues' => [
                        Response::HTTP_OK => 'Der Spieler wurde erfolgreich gelöscht und ' .
                            'die zuletzt bekannte Ressourcenrepräsentation ist in der Antwort enthalten.',
                        Response::HTTP_NOT_FOUND => 'Der Spieler wurde nicht gefunden.',
                        Response::HTTP_INTERNAL_SERVER_ERROR => 'Der Spieler konnte nicht auf dem Server gespeichert werden.',
                        Response::HTTP_UNAUTHORIZED => 'Der Spieler kann nur von sich selbst gelöscht werden, entsprechend muss dieser sich vorher authentifizieren.',
                        Response::HTTP_FORBIDDEN => 'Es wurden falsche Basic Auth Daten angegeben. Ein Zugriff wurde verweigert.',
                    ],
                ],
            ],
        ];
    }

    /**
     * Diese Funktion beschreibt GET /users/.
     *
     * @param Request $request Das Anfrageobjekt
     *
     * @return array|Hal
     */
    public function listAction(Request $request)
    {
        $retVal = [];
        foreach ($this->getUserManager()->listAll() as $userId) {
            try {
                $link = $this->getUrlGenerator()->generate(
                    'user_detail',
                    ['id' => $userId]
                );

                $retVal[] = [
                    'ref' => 'user',//:'.$userId,
                    'link' => $link,
                    'id' => $userId,
                ];
            } catch (\InvalidArgumentException $e) {
            }
        }

        return $this->prepareResponseReturn($retVal, $request);
    }

    /**
     * @return UserManager
     */
    protected function getUserManager()
    {
        return $this->userManager;
    }

    /**
     * Diese Funktion bereitet die Daten der Action Funktionen auf.
     *
     * @param mixed $data
     * @param Request $request
     *
     * @return array|Hal
     */
    protected function prepareResponseReturn($data, Request $request)
    {
        if ($data instanceof User) {
            // Wenn von einer Aktion ein User Objekt zurückgegeben wurde,
            // dann soll eine Detailansicht der Ressource zurückgegeben werden.
            // Entsprechend erstellen wir diese durch den UserTransformer.
            $userArray = (new UserTransformer())->toArray($data);
            if (in_array(current($request->getAcceptableContentTypes()), ['text/html', '*/*'], true)) {
                // Bei einer HTML Ansicht wird das Array direkt zurückgegeben und in den Twig-Templates
                // zum rendern der HTML Ansichten verwendet.
                // Das Embedding wird in der HTML Ansicht nicht vorgesehen.
                return $userArray;
            } else {
                // Sonstige Formate werden durch den Hypertext Application Language Layer behandelt.
                $hal = new Hal(
                    $request->getPathInfo(),
                    $userArray
                );

                // Dabei wird auch das Embedding mit beachtet.
                $this->handleEmbedding($request, $hal);

                return $hal;
            }
        }

        // Wenn die Daten ein Array sind, so ist die Rückgabe eine Listenansicht
        if (is_array($data)) {
            if (in_array(current($request->getAcceptableContentTypes()), ['text/html', '*/*'], true)) {
                // Für die HTML Listenansicht werden URIs der Matches ausgelesen
                // und für die Twig Engine als Array zurückgegeben
                $retVal = [];

                foreach ($data as $dataDetails) {
                    $retVal[] = $dataDetails['link'];
                }

                return $retVal;
            } else {
                // Für alle anderen Formate werden die Links durch der HAL Layer abgebildet.
                $hal = new Hal($request->getPathInfo());
                foreach ($data as $dataDetails) {
                    $hal->addLink($dataDetails['ref'], $dataDetails['link'], array(), true);
                }

                // Embedding von SubRessourcen
                $this->handleEmbedding($request, $hal);

                return $hal;
            }
        }

        return null;
    }

    /**
     * Diese Funktion kümmert sich um das Embedding von Ressourcen anhand der Queryparameter des Requests.
     *
     * @todo Könnte besser gelöst werden, indem das generisch im VIEW Event behandelt wird.
     *       Dabei könnte ein SubRequest durch den HTTP Kernel an den Detailendpunkt gesendet werden.
     *
     * @param Request $request
     * @param Hal $hal
     */
    protected function handleEmbedding(Request $request, Hal $hal)
    {
        $implyEmbed = false;

        // Parsen der Query Parameter und aktivieren des Embeddings
        foreach ($request->query as $name => $value) {
            // handle embedding
            if (strpos($name, 'embed') !== false) {
                $implyEmbed = true;
            }
        }

        if ($implyEmbed) {
            $userTransformer = new UserTransformer();
            $embedding = []; // Enthält URI => Ressourcendarstellung
            $links = $hal->getLinks(); // alle _links
            if (array_key_exists('user', $links)) {
                /*
                 * Wenn Verlinkungen der Relation match existieren (vgl ::prepareResponseReturn),
                 * dann wollen wir diese Embedden, da $embed['resource'] gesetzt wurde.
                 */
                foreach ($links['user'] as $halLink) {
                    /* @var HalLink $halLink */
                    // Wir versuchen den User anhand der URI zu laden
                    $user = $this->getUserManager()->loadByResource($halLink->getUri());
                    if ($user) {
                        // War das erfolgreich, speicher wir dessen Detailansicht anhand der URI in $embedding.
                        $embedding[$halLink->getUri()] = $userTransformer->toArray($user);
                    }
                }
            }

            if ($embedding) {
                // Sofern es Daten zu embedden gibt, werden diese in _embedded geschrieben.
                $data = $hal->getData();
                $data['_embedded'] = $embedding;
                $hal->setData($data);
            }
        }
    }

    /**
     * Diese Funktion beschreibt POST /users/.
     *
     * @param Request $request
     * @param Response $response
     *
     * @return array|Hal
     * @throws HttpConflictException wenn ein Validierungsfehler auftritt.
     * @throws HttpException wenn das Objekt nicht gespeichert werden konnte.
     */
    public function createAction(Request $request, Response $response)
    {
        $user = new User();

        if (!$request->request->has('name')) {
            throw new HttpConflictException(
                10001,
                'You must specify a name.',
                'name',
                'The user has to specify a name, ' .
                'so he can authenticate/authorize himself with the API later on.'
            );
        }

        if (!$request->request->has('password')) {
            throw new HttpConflictException(
                10002,
                'You must specify a password.',
                'password',
                'The user has to specify a password, ' .
                'so he can authenticate/authorize himself with the API later on.'
            );
        }

        $user->setName($request->get('name'));
        $user->setPassword($request->get('password'));

        if ($this->getUserManager()->save($user)) {
            $response->setStatusCode(Response::HTTP_CREATED);
            $response->headers->set(
                'Location',
                $this->getUrlGenerator()->generate(
                    'user_detail',
                    ['id' => $user->getId()]
                )
            );

            return $this->prepareResponseReturn($user, $request);
        }

        throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Diese Funktion beschreibt GET /users/{id}.
     *
     * @param Request $request
     * @param int $id
     * @return array|Hal
     */
    public function detailAction(Request $request, $id)
    {
        $user = $this->getUserManager()->load($id);

        if ($user) {
            return $this->prepareResponseReturn($user, $request);
        }

        throw new NotFoundHttpException('Could not find user ' . $id);
    }

    /**
     * Diese Funktion beschreibt PUT /users/{id}.
     *
     * @param Request $request
     * @param int $id
     *
     * @return array|Hal
     *
     * @throws HttpConflictException wenn ein Validierungsfehler auftritt.
     * @throws HttpException wenn das Objekt nicht gespeichert werden konnte.
     */
    public function replaceAction(Request $request, $id)
    {
        if (!$request->request->has('name')) {
            throw new HttpConflictException(
                10011,
                'You must specify a name.',
                'name',
                'The user has to specify a name, ' .
                'so he can authenticate/authorize himself with the API later on.'
            );
        }

        if (!$request->request->has('password')) {
            throw new HttpConflictException(
                10012,
                'You must specify a password.',
                'password',
                'The user has to specify a password, ' .
                'so he can authenticate/authorize himself with the API later on.'
            );
        }
        $user = $this->getUserManager()->load($id);

        if (!$user) {
            $user = new User();
        }

        $user->setName($request->request->get('name'));
        $user->setPassword($request->request->get('password'));

        if ($this->getUserManager()->save($user)) {
            return $this->prepareResponseReturn($user, $request);
        }

        throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Diese Funktion beschreibt POST /users/{id}.
     *
     * @param Request $request
     * @param int $id
     *
     * @return array|Hal
     *
     * @throws HttpException wenn das Objekt nicht gespeichert werden konnte.
     * @throws NotFoundHttpException wenn der Benutzer nicht gefunden werden konnte.
     */
    public function updateAction(Request $request, $id)
    {
        $user = $this->getUserManager()->load($id);

        if ($user) {
            if ($request->request->has('name')) {
                $user->setName($request->request->get('name'));
            }

            if ($request->request->has('password')) {
                $user->setPassword($request->request->get('password'));
            }

            if ($this->getUserManager()->save($user)) {
                return $this->prepareResponseReturn($user, $request);
            }

            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        throw new NotFoundHttpException('Could not find user ' . $id);
    }

    /**
     * Diese Funktion beschreibt DELETE /users/{id}.
     *
     * @param Request $request
     * @param int $id
     *
     * @return array|Hal
     *
     * @throws NotFoundHttpException wenn der Benutzer nicht gefunden werden konnte.
     * @throws HttpException wenn das Objekt nicht gelöscht werden konnte
     */
    public function deleteAction(Request $request, $id)
    {
        /** @var User $match */
        $user = $this->getUserManager()->load($id);
        if (!$user) {
            throw new NotFoundHttpException('Could not find user ' . $id);
        }

        if ($this->getUserManager()->delete($user)) {
            return $this->prepareResponseReturn($user, $request);
        }

        throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
