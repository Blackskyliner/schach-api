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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Dieser Controller implementiert alle Funktionen bezüglich der Routen
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

    /** Importiere standard Getter/Setter für das RouterAwareInterface */
    use UrlGeneratorAwareTrait;

    /**
     * UserController constructor.
     * @param UserManager $userManager
     */
    public function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    /**
     * @return UserManager
     */
    protected function getUserManager()
    {
        return $this->userManager;
    }

    /**
     * Diese Funktion beschreibt GET /users/
     *
     * @return array
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
                    'id' => $userId
                ];
            } catch (\InvalidArgumentException $e) {
            }
        }

        return $this->prepareResponseReturn($retVal, $request);
    }

    /**
     * Diese Funktion beschreibt POST /users/
     *
     * @param Request $request
     * @param Response $response
     * @return array|Hal
     */
    public function createAction(Request $request, Response $response)
    {
        $user = new User();

        if (!$request->request->has('name')) {
            throw new HttpConflictException(
                10001,
                'You must specify a name.',
                'name',
                'The user has to specify a name, '.
                'so he can authenticate/authorize himself with the API later on.'
            );
        }

        if (!$request->request->has('password')) {
            throw new HttpConflictException(
                10002,
                'You must specify a password.',
                'password',
                'The user has to specify a password, '.
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
     * Diese Funktion beschreibt GET /users/{id}
     *
     * @param integer $id
     * @return array
     * @throws NotFoundHttpException wenn der Benutzer nicht gefunden werden konnte.
     */
    public function detailAction(Request $request, $id)
    {
        $user = $this->getUserManager()->load($id);

        if ($user) {
            return $this->prepareResponseReturn($user, $request);
        }

        throw new NotFoundHttpException('Could not find user '.$id);
    }

    /**
     * Diese Funktion beschreibt PUT /users/{id}
     *
     * @param Request $request
     * @param integer $id
     */
    public function replaceAction(Request $request, $id)
    {
        if (!$request->request->has('name')) {
            throw new HttpConflictException(
                10011,
                'You must specify a name.',
                'name',
                'The user has to specify a name, '.
                'so he can authenticate/authorize himself with the API later on.'
            );
        }

        if (!$request->request->has('password')) {
            throw new HttpConflictException(
                10012,
                'You must specify a password.',
                'password',
                'The user has to specify a password, '.
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
     * Diese Funktion beschreibt POST /users/{id}
     *
     * @param Request $request
     * @param integer $id
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

        throw new NotFoundHttpException('Could not find user '.$id);
    }

    /**
     * Diese Funktion beschreibt DELETE /users/{id}
     *
     * @param integer $id
     * @return Response
     */
    public function deleteAction(Request $request, $id)
    {
        $user = $this->getUserManager()->load($id);

        if ($user) {
            $this->getUserManager()->delete($user);
            return $this->prepareResponseReturn($user, $request);
        }

        throw new NotFoundHttpException('Could not find user '.$id);
    }

    /**
     * Diese Funktion bereitet die Daten der Action Funktionen auf.
     *
     * @param mixed $data
     * @param Request $request
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
            if (array_key_exists('match', $links)) {
                /*
                 * Wenn Verlinkungen der Relation match existieren (vgl ::prepareResponseReturn),
                 * dann wollen wir diese Embedden, da $embed['resource'] gesetzt wurde.
                 */
                foreach ($links['user'] as $halLink) {
                    /** @var HalLink $halLink */
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
                    'description' => 'Show a list of users.',
                    'returnValues' => [
                        Response::HTTP_OK => 'The response contains a list of all users resource URIs.',
                    ]
                ],
                'POST' => [
                    'method' => 'controller.user:createAction',
                    'description' => 'Create a user.',
                    'content-types' => [
                        'application/x-www-form-urlencoded',
                        'application/json',
                        'text/xml',
                    ],
                    'parameters' => [
                        'name' => [
                            'required' => true,
                            'type' => 'text/plain',
                            'description' => 'Name of the user',
                        ],
                        'password' => [
                            'required' => true,
                            'type' => 'text/plain',
                            'description' => 'Password of the user',
                        ],
                    ],
                    'example' => [
                        'name' => 'Bernd',
                        'password' => '5e(R37'
                    ],
                    'returnValues' => [
                        Response::HTTP_CREATED => 'The user resource was created successfully and '.
                            'the current resource representation is contained in the response body.',
                        Response::HTTP_CONFLICT => 'The resulting user resource is not in a valid state.',
                        Response::HTTP_INTERNAL_SERVER_ERROR => 'The user resource could not '.
                            'be persisted on the server.',
                    ]
                ],
            ],
            '/{id}' => [
                'GET' =>  [
                    'method' => 'controller.user:detailAction',
                    'description' => 'Show the details of a user.',
                    'returnValues' => [
                        Response::HTTP_OK => 'The user resource was found and is contained in the response body.',
                        Response::HTTP_NOT_FOUND => 'The user resource was not found.',
                    ]
                ],
                'PUT' => [
                    'method' => 'controller.user:replaceAction',
                    'content-types' => [
                        'application/x-www-form-urlencoded',
                        'application/json',
                        'text/xml',
                    ],
                    'description' => 'Replaces or creates the user.',
                    'parameters' => [
                        'name' => [
                            'required' => true,
                            'type' => 'text/plain',
                            'description' => 'Name of the user',
                        ],
                        'password' => [
                            'required' => true,
                            'type' => 'text/plain',
                            'description' => 'Password of the user',
                        ],
                    ],
                    'example' => [
                        'name' => 'Bernd',
                        'password' => '5e(R37'
                    ],
                    'returnValues' => [
                        Response::HTTP_OK => 'The user resource was replaced successfully and '.
                            'the current resource representation is contained in the response body.',
                        Response::HTTP_CONFLICT => 'The resulting user resource is not in a valid state.',
                        Response::HTTP_INTERNAL_SERVER_ERROR => 'The user resource could not '.
                            'be persisted on the server.',
                    ]
                ],
                'POST' => [
                    'method' => 'controller.user:updateAction',
                    'content-types' => [
                        'application/x-www-form-urlencoded',
                        'application/json',
                        'text/xml',
                    ],
                    'description' => 'Update the user. May be used by a HTML-Form.',
                    'parameters' => [
                        'name' => [
                            'required' => false,
                            'type' => 'text/plain',
                            'description' => 'Name of the user, if not specified or empty it will not be changed.',
                        ],
                        'password' => [
                            'required' => false,
                            'type' => 'text/plain',
                            'description' => 'Password of the user, if not specified or empty it will not be changed.',
                        ],
                    ],
                    'example' => [
                        'name' => 'Bernd',
                        'password' => '5e(R37'
                    ],
                    'returnValues' => [
                        Response::HTTP_OK => 'The user resource was updated successfully and '.
                            'the current resource representation is contained in the response body.',
                        Response::HTTP_NOT_FOUND => 'The user resource was not found.',
                        Response::HTTP_CONFLICT => 'The resulting user resource is not in a valid state.',
                        Response::HTTP_INTERNAL_SERVER_ERROR => 'The user resource could not '.
                            'be persisted on the server.',
                    ]
                ],
                'DELETE' => [
                    'method' => 'controller.user:deleteAction',
                    'description' => 'Deletes the user.',
                    'returnValues' => [
                        Response::HTTP_OK => 'The user resource was deleted successfully and '.
                            'the last known resource representation is contained in the response body.',
                        Response::HTTP_NOT_FOUND => 'The user resource was not found.',
                        Response::HTTP_INTERNAL_SERVER_ERROR => 'The user resource could not '.
                            'be deleted from the server.',
                    ]
                ],
            ]
        ];
    }
}
