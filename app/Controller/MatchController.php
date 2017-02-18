<?php

namespace Htwdd\Chessapi\Controller;

use Htwdd\Chessapi\DataTransformer\MatchTransformer;
use Htwdd\Chessapi\DataTransformer\UserTransformer;
use Htwdd\Chessapi\Entity\Match;
use Htwdd\Chessapi\Entity\MatchManager;
use Htwdd\Chessapi\Entity\User;
use Htwdd\Chessapi\Entity\UserManager;
use Htwdd\Chessapi\Exception\HttpConflictException;
use Htwdd\Chessapi\Exception\InvalidChessStateException;
use Htwdd\Chessapi\Service\ChessService;
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
 *  - /match/
 *  - /match/{id}
 *
 * Das Mapping, welche Funktion auf welche Route und HTTP Methode gerufen wird, geschieht
 * in der getRoutes() Funktion.
 */
class MatchController implements UrlGeneratorAwareInterface
{
    /**
     * Der Matchmanager wird benötigt, um ein Match laden zu können.
     *
     * @var MatchManager
     */
    private $matchManager;

    /**
     * Der Usermanager wird aufgrund des Embeddings benötigt.
     * Da dafür die Benutzerdaten geladen werden müssen.
     *
     * @var UserManager
     */
    private $userManager;

    /**
     * Der ChessService ist für die Validierung der Spielzüge verantwortlich.
     *
     * @var ChessService
     */
    private $chessService;

    /** Importiere standard Getter/Setter für das RouterAwareInterface */
    use UrlGeneratorAwareTrait;

    /**
     * MatchController constructor.
     *
     * @param MatchManager $matchManager
     * @param UserManager $userManager
     * @param ChessService $chessService
     */
    public function __construct(MatchManager $matchManager, UserManager $userManager, ChessService $chessService)
    {
        $this->chessService = $chessService;
        $this->matchManager = $matchManager;
        $this->userManager = $userManager;
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
        $dummy = new Match();
        return [
            '/' => [
                'GET' => [
                    'method' => 'controller.match:listAction',
                    'description' => 'Gibt eine Liste von URIs aller Partien zurück. Dabei können die ' .
                        'GET Parameter embed, embed-white und embed-black verwendet werden, ' .
                        'um die Partien und deren jeweilige Spieler in die Antwort direkt mit einzubinden.',
                    'returnValues' => [
                        Response::HTTP_OK => 'Die Antwort enthält eine Liste von URIs aller Partien.',
                    ]
                ],
                'POST' => [
                    'method' => 'controller.match:createAction',
                    'description' => 'Erstellt eine Partie',
                    'content-types' => [
                        'application/x-www-form-urlencoded',
                        'application/json',
                        'text/xml',
                    ],
                    'parameters' => [
                        'white' => [
                            'required' => false,
                            'type' => 'application/user',
                            'description' => 'URI zu einem Benuntzer/Spieler, welcher die weißen Figuren repräsentieren soll.'
                        ],
                        'black' => [
                            'required' => false,
                            'type' => 'application/user',
                            'description' => 'URI zu einem Benuntzer/Spieler, welcher die schwarten Figuren repräsentieren soll.'
                        ],
                        'start' => [
                            'required' => false,
                            'type' => 'text/fen',
                            'description' => 'Die Startsituation der Schachpartie in FEN',
                            'default' => $dummy->getStart(),
                        ],
                        'history' => [
                            'required' => false,
                            'type' => 'array of text/san',
                            'description' => 'Züge, welche in chronologischer Reihenfolge auf die Startsituation angewendet werden sollen',
                            'default' => $dummy->getHistory(),
                        ]
                    ],
                    'example' => [
                        'white' => '/users/1',
                        'black' => '/users/2',
                        'start' => $dummy->getStart(),
                        'history' => [
                            'e4',
                            'd6',
                            'd4',
                            'g6'
                        ]
                    ],
                    'returnValues' => [
                        Response::HTTP_CREATED => 'Die Partie wurde erfolgreich erstellt und ' .
                            'die aktuelle Ressourcenrepräsentation ist in der Antwort enthalten.',
                        Response::HTTP_CONFLICT => 'Die aus den Parametern resultierende Partie ist ungültig.',
                        Response::HTTP_INTERNAL_SERVER_ERROR => 'Die Partie konnte nicht auf dem Server gespeichert werden.',
                    ]
                ],
            ],
            '/{id}' => [
                'GET' => [
                    'method' => 'controller.match:detailAction',
                    'description' => 'Gibt die Details einer Partie zurück. ' .
                        'Dabei können die GET Parameter ?embed-white und ?embed-black verwendet werden, ' .
                        'um die Ressource des jeweiligen Spielers in der Antwort mit einzubinden.',
                    'returnValues' => [
                        Response::HTTP_OK => 'Die Partie wurde gefunden befindet sich in der Antwort.',
                        Response::HTTP_NOT_FOUND => 'Die Partie wurde nicht gefunden.',
                    ]
                ],
                'PUT' => [
                    'method' => 'controller.match:replaceAction',
                    'content-types' => [
                        'application/x-www-form-urlencoded',
                        'application/json',
                        'text/xml',
                    ],
                    'description' => 'Überschreibt oder erstellt eine Partie mit den angegebenen Parametern.',
                    'parameters' => [
                        'white' => [
                            'required' => false,
                            'type' => 'application/user',
                            'description' => 'URI zu einem Benuntzer/Spieler, welcher die weißen Figuren repräsentieren soll.'
                        ],
                        'black' => [
                            'required' => false,
                            'type' => 'application/user',
                            'description' => 'URI zu einem Benuntzer/Spieler, welcher die schwarten Figuren repräsentieren soll.'
                        ],
                        'start' => [
                            'required' => false,
                            'type' => 'text/fen',
                            'description' => 'Die Startsituation der Schachpartie in FEN',
                            'default' => $dummy->getStart(),
                        ],
                        'history' => [
                            'required' => false,
                            'type' => 'array of text/san',
                            'description' => 'Züge, welche in chronologischer Reihenfolge auf die Startsituation angewendet werden sollen',
                            'default' => $dummy->getHistory(),
                        ]
                    ],
                    'example' => [
                        'white' => '/users/1',
                        'black' => '/users/2',
                        'start' => $dummy->getStart(),
                        'history' => [
                            'e4',
                            'd6',
                            'd4',
                            'g6'
                        ]
                    ],
                    'returnValues' => [
                        Response::HTTP_OK => 'Die Partie wurde erfolgreich ersetzt und ' .
                            'die aktuelle Ressourcenrepräsentation ist in der Antwort enthalten.',
                        Response::HTTP_CREATED => 'Die Partie wurde erfolgreich erstellt und ' .
                            'die aktuelle Ressourcenrepräsentation ist in der Antwort enthalten.',
                        Response::HTTP_CONFLICT => 'Die aus den Parametern resultierende Partie ist ungültig.',
                        Response::HTTP_INTERNAL_SERVER_ERROR => 'Die Partie konnte nicht auf dem Server gespeichert werden.',
                    ]
                ],
                'POST' => [
                    'method' => 'controller.match:updateAction',
                    'content-types' => [
                        'application/x-www-form-urlencoded',
                        'application/json',
                        'text/xml',
                    ],
                    'description' => 'Aktualisiert die Partie anhand der übergebenen Parameter.',
                    'parameters' => [
                        'white' => [
                            'required' => false,
                            'type' => 'application/user',
                            'description' => 'URI zu einem Benuntzer/Spieler, welcher die weißen Figuren repräsentieren soll.'
                        ],
                        'black' => [
                            'required' => false,
                            'type' => 'application/user',
                            'description' => 'URI zu einem Benuntzer/Spieler, welcher die schwarten Figuren repräsentieren soll.'
                        ],
                        'start' => [
                            'required' => false,
                            'type' => 'text/fen',
                            'description' => 'Die Startsituation der Schachpartie in FEN',
                            'default' => $dummy->getStart(),
                        ],
                        'history' => [
                            'required' => false,
                            'type' => 'array of text/san',
                            'description' => 'Züge, welche in chronologischer Reihenfolge auf die Startsituation angewendet werden sollen',
                            'default' => $dummy->getHistory(),
                        ]
                    ],
                    'example' => [
                        'white' => '/users/1',
                        'black' => '/users/2',
                        'start' => $dummy->getStart(),
                        'history' => [
                            'e4',
                            'd6',
                            'd4',
                            'g6'
                        ]
                    ],
                    'returnValues' => [
                        Response::HTTP_OK => 'Die Partie wurde erfolgreich aktualisiert und ' .
                            'die aktuelle Ressourcenrepräsentation ist in der Antwort enthalten.',
                        Response::HTTP_NOT_FOUND => 'Die Partie wurde nicht gefunden.',
                        Response::HTTP_CONFLICT => 'Die aus den Parametern resultierende Partie ist ungültig.',
                        Response::HTTP_INTERNAL_SERVER_ERROR => 'Die Partie konnte nicht auf dem Server gespeichert werden.',
                    ]
                ],
                'PATCH' => [
                    'method' => 'controller.match:patchAction',
                    'content-types' => [
                        'text/san',
                    ],
                    'description' => 'Fügt einen Zug in der SAN zu den Zügen der Partie hinzu. ' .
                        'Sollte eine KI konfiguriert sein, ' .
                        'so kann man diese einen Zug spielen lassen, indem man "KI 1000" als Notation eingibt. ' .
                        'Die 1000 steht dabei für 1000ms Bedenkzeit.',
                    'example' => 'e4',
                    'returnValues' => [
                        Response::HTTP_OK => 'Die Partie wurde erfolgreich aktualisiert und ' .
                            'die aktuelle Ressourcenrepräsentation ist in der Antwort enthalten.',
                        Response::HTTP_NOT_FOUND => 'Die Partie wurde nicht gefunden.',
                        Response::HTTP_CONFLICT => 'Die aus den Parametern resultierende Partie ist ungültig.',
                    ]
                ],
                'DELETE' => [
                    'method' => 'controller.match:deleteAction',
                    'description' => 'Löscht eine Partie.',
                    'returnValues' => [
                        Response::HTTP_OK => 'Die Partie wurde erfolgreich gelöscht und ' .
                            'die zuletzt bekannte Ressourcenrepräsentation ist in der Antwort enthalten.',
                        Response::HTTP_NOT_FOUND => 'Die Partie wurde nicht gefunden.',
                        Response::HTTP_INTERNAL_SERVER_ERROR => 'Die Partie konnte nicht auf dem Server gespeichert werden.',
                    ]
                ],
            ]
        ];
    }

    /**
     * Diese Funktion beschreibt GET /matches/
     *
     * @param Request $request
     * @return array|Hal
     */
    public function listAction(Request $request)
    {
        $retVal = [];
        // Es werden alle Matches über den manager geholt
        foreach ($this->getMatchManager()->listAll() as $matchId) {
            try {
                // Dann wird die URI zur Detailansicht generiert.
                $link = $this->getUrlGenerator()->generate(
                    'match_detail',
                    ['id' => $matchId]
                );

                // Das Format des Arrays wird in prepareResponseReturn ausgewertet.
                $retVal[] = [
                    'ref' => 'match',
                    'link' => $link
                ];
            } catch (\InvalidArgumentException $e) {
                // Sollte eine ungültige URI generiert werden so wird dieser Eintrag verworfen.
            }
        }

        return $this->prepareResponseReturn($retVal, $request);
    }

    /**
     * @return MatchManager
     */
    protected function getMatchManager()
    {
        return $this->matchManager;
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
        if ($data instanceof Match) {
            // Wenn von einer Aktion ein Match Objekt zurückgegeben wurde,
            // dann soll eine Detailansicht der Ressource zurückgegeben werden.
            // Entsprechend erstellen wir diese durch den MatchTransformer.
            $matchArray = (new MatchTransformer())->toArray($data);
            if (in_array(current($request->getAcceptableContentTypes()), ['text/html', '*/*'], true)) {
                // Bei einer HTML Ansicht wird das Array direkt zurückgegeben und in den Twig-Templates
                // zum rendern der HTML Ansichten verwendet.
                // Das Embedding wird in der HTML Ansicht nicht vorgesehen.
                return $matchArray;
            } else {
                // Sonstige Formate werden durch den Hypertext Application Language Layer behandelt.
                $hal = new Hal(
                    $request->getPathInfo(),
                    $matchArray
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
        // Diese Parameter definieren, was embedded werden soll
        $embed['resource'] = false;
        $embed['white'] = false;
        $embed['black'] = false;

        // Parsen der Query Parameter und aktivieren der jeweils zutreffenden embedding Parameter
        foreach ($request->query as $name => $value) {
            // handle embedding
            if (strpos($name, 'embed') !== false) {
                $embed['resource'] = true;
                if (strpos($name, '-') !== false) {
                    // handle sub fields
                    if (strpos($name, 'white') !== false) {
                        $embed['white'] = true;
                    }
                    if (strpos($name, 'black') !== false) {
                        $embed['black'] = true;
                    }
                }
            }
        }

        if ($embed['resource']) {
            $userTransformer = new UserTransformer();
            $matchTransformer = new MatchTransformer();
            $embedding = []; // Enthält URI => Ressourcendarstellung
            $links = $hal->getLinks(); // alle _links

            if (array_key_exists('match', $links)) {
                /*
                 * Wenn Verlinkungen der Relation match existieren (vgl ::prepareResponseReturn),
                 * dann wollen wir diese Embedden, da $embed['resource'] gesetzt wurde.
                 */
                foreach ($links['match'] as $halLink) {
                    /** @var HalLink $halLink */
                    /** @var Match $match */

                    // Wir versuchen das Match anhand der URI zu laden
                    $match = $this->getMatchManager()->loadByResource($halLink->getUri());
                    if ($match) {
                        // War das erfolgreich, speicher wir dessen Detailansicht anhand der URI in $embedding.
                        $embedding[$halLink->getUri()] = $matchTransformer->toArray($match);

                        // danach prüfen wir ob white und black im Match gesetzt sind.
                        if ($embed['white']
                            && $match->getWhite()
                            && !array_key_exists($match->getWhite(), $embedding)
                        ) {
                            // Sofern white definiert ist, laden wir anhand der URI den User
                            // und fügen dessen Detailansicht analog in $embedding hinzu.
                            $user = $this->getUserManager()->loadByResource($match->getWhite());
                            $embedding[$match->getWhite()] = $userTransformer->toArray($user);
                        }
                        if ($embed['black']
                            && $match->getBlack()
                            && !array_key_exists($match->getBlack(), $embedding)
                        ) {
                            // analog white
                            $user = $this->getUserManager()->loadByResource($match->getBlack());
                            $embedding[$match->getBlack()] = $userTransformer->toArray($user);
                        }
                    }
                }
            }

            if ($data = $hal->getData()) {
                // Diser Fall ermöglicht das embedding in der Detailansicht.
                // Bzw. in einer Funktion, welche eine Detailansicht zurückzugeben versucht.
                // Entsprechend laden wir analog zu Listenansicht die Benutzer
                // der in $data gesetzten Detailansicht.
                if ($embed['white']
                    && array_key_exists('white', $data)
                    && $data['white']
                    && !array_key_exists($data['white'], $embedding)
                ) {
                    $user = $this->getUserManager()->loadByResource($data['white']);
                    $embedding[$data['white']] = $userTransformer->toArray($user);
                }
                if ($embed['black']
                    && array_key_exists('black', $data)
                    && $data['black']
                    && !array_key_exists($data['black'], $embedding)
                ) {
                    $user = $this->getUserManager()->loadByResource($data['black']);
                    $embedding[$data['black']] = $userTransformer->toArray($user);
                }
            }

            // Sofern es Daten zu embedden gibt, werden diese in _embedded geschrieben.
            if ($embedding) {
                $data = $hal->getData();
                $data['_embedded'] = $embedding;
                $hal->setData($data);
            }
        }
    }

    /**
     * @return UserManager
     */
    protected function getUserManager()
    {
        return $this->userManager;
    }

    /**
     * Diese Funktion beschreibt POST /matches/
     *
     * @param Request $request
     * @param Response $response
     *
     * @return array|Hal
     *
     * @throws HttpConflictException wenn die Partie nicht erfolgreich validiert werden konnte.
     * @throws HttpException wenn das Objekt nicht gespeichert werden konnte
     */
    public function createAction(Request $request, Response $response)
    {
        // Alle Parameter aus dem Request auslesen
        $white = $request->request->get('white', null);
        $black = $request->request->get('black', null);
        $start = $request->request->get('start', null);
        $history = $request->request->get('history', []);

        // Erstellen einer neuen Partie und setzen der übergebenen Parameter
        $match = new Match();

        // Sofern die Spieler angegeben wurden, müssen diese auf Gültigkeit überprüft werden
        if ($white && $this->checkUser($white)) {
            $match->setWhite($white);
        }
        if ($black && $this->checkUser($black)) {
            $match->setBlack($black);
        }

        if ($start) {
            $match->setStart($start);
        }

        if ($history) {
            $match->setHistory($this->unpackHistory($history));
        }

        // Wir prüfen auf die Gültigkeit der Schachzüge und speichern das Match, wenn diese gültig sind.
        if ($this->checkMatch($match) && $this->getMatchManager()->save($match)) {
            /*
             * Sollte das Speichern erfolgreich sein, so wird der Status der Antwort auf 201 CREATED gesetzt.
             * Zudem wird die Route zum Detailendpunkt generiert und als Location Header der Antwort hinzugefügt.
             */
            $response->setStatusCode(Response::HTTP_CREATED);
            $response->headers->set(
                'Location',
                $this->getUrlGenerator()->generate(
                    'match_detail',
                    ['id' => $match->getId()]
                )
            );

            return $this->prepareResponseReturn($match, $request);
        }

        // Hier landet man nur, wenn das Speichern nicht erfolgreich war.
        // Es wird ein interner Serverfehler (500) geworfen.
        throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Diese Methode prüft, ob die übergebene URI einem Benutzer im System entspricht.
     *
     * @param string $uri zu einem Benutzer
     * @throws HttpConflictException wenn der Benutzer nicht existiert.
     * @return bool
     */
    private function checkUser($uri)
    {
        $resource = $this->userManager->loadByResource($uri);
        if ($resource === null || !$resource instanceof User) {
            throw new HttpConflictException(
                '20010',
                sprintf('The specified user "%s" could not be fetched.', $uri),
                null,
                'Make sure the given user was correctly specified and exists.' . PHP_EOL .
                'A user should be specified as an relative URI within the api. e.g. /users/1' . PHP_EOL
            );
        }

        return true;
    }

    /**
     * Diese Funktion macht aus einer History, welche über einen Request in die Funktionen gegeben werden,
     * ein Array, welches direkt in ein Match gegeben werden kann.
     *
     * @param string|array $history
     * @returns array
     */
    private function unpackHistory($history)
    {
        /*
         * Die Historie wird als array gespeichert.
         * Bei der Übertragung wird davon ausgegangen, dass einzelne Züge der Historie
         * durch eine NEWLINE (\n) getrennt sind.
         * Dieses Verhalten entspricht dabei einer HTML Textbox.
         *
         * Entsprechend muss diese übergabe von Leerzeichen und \r befreit werden,
         * da diese in der SchachEngine sonst zu Problemen führen könnten.
         */
        if (is_array($history)) {
            return $history;
        } else {
            return explode(
                "\n",
                str_replace(
                    array("\r", ' '),
                    array('', ''),
                    $history
                )
            );
        }
    }

    /**
     * Diese Methode prüft, ob das übergebene Match eine gültige Schachhistorie besitzt.
     *
     * @param Match $match
     * @throws HttpConflictException wenn ein ungültiger Zug entdeckt wurde.
     * @return bool
     */
    private function checkMatch(Match $match)
    {
        try {
            $this->chessService->validate($match->getStart(), $match->getHistory());
        } catch (InvalidChessStateException $e) {
            throw new HttpConflictException(
                '20020',
                $e->getMessage(),
                'history',
                'Make sure the given history only got valid moves in the standard algebraic notation.' . PHP_EOL .
                'If you think the history is correct, there may be an error in the Chess Engine...'
            );
        }

        return true;
    }

    /**
     * Diese Funktion beschreibt GET /matches/{id}
     *
     * @param Request $request
     * @param $id
     * @return array|Hal
     *
     * @throws NotFoundHttpException wenn die Partie nicht gefunden wurde
     */
    public function detailAction(Request $request, $id)
    {
        $match = $this->getMatchManager()->load($id);

        if ($match) {
            return $this->prepareResponseReturn($match, $request);
        }

        throw new NotFoundHttpException('Could not find match ' . $id);
    }

    /**
     * Diese Funktion beschreibt POST /matches/{id}
     *
     * @param Request $request
     * @param $id
     * @return array|Hal
     *
     * @throws NotFoundHttpException wenn die Partie nicht gefunden wurde
     * @throws HttpConflictException wenn die Partie nicht erfolgreich validiert werden konnte.
     * @throws HttpException wenn das Objekt nicht gespeichert werden konnte
     */
    public function updateAction(Request $request, $id)
    {
        /** @var Match $match */
        $match = $this->matchManager->load($id);
        $white = $request->request->get('white', null);
        $black = $request->request->get('black', null);
        $start = $request->request->get('start', null);
        $history = $request->request->get('history', []);

        if (!$match) {
            throw new NotFoundHttpException('Could not find match ' . $id);
        }

        if ($white && $this->checkUser($white)) {
            $match->setWhite($white);
        }
        if ($black && $this->checkUser($black)) {
            $match->setBlack($black);
        }
        if ($start) {
            $match->setStart($start);
        }
        if ($history) {
            $match->setHistory($this->unpackHistory($history));
        } else {
            if ($start) {
                $match->setHistory(array());
            }
        }

        if ($this->checkMatch($match) && $this->matchManager->save($match)) {
            return $this->prepareResponseReturn($match, $request);
        }

        throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Diese Funktion beschreibt PUT /matches/{id}
     *
     * @param Request $request
     * @param $id
     * @return array|Hal
     *
     * @throws HttpConflictException wenn die Partie nicht erfolgreich validiert werden konnte.
     * @throws HttpException wenn das Objekt nicht gespeichert werden konnte
     */
    public function replaceAction(Request $request, $id)
    {
        /** @var Match $match */
        $match = $this->matchManager->load($id);
        $white = $request->request->get('white', null);
        $black = $request->request->get('black', null);
        $start = $request->request->get('start', null);
        $history = $request->request->get('history', []);

        if (!$match) {
            // Sollte kein Match gefunden werden, so wird eines mit der in der URI angegebene ID angelegt
            $match = new Match();
            $this->matchManager->setIdentifier($match, $id);
        }

        if ($white && $this->checkUser($white)) {
            $match->setWhite($white);
        }
        if ($black && $this->checkUser($black)) {
            $match->setBlack($black);
        }
        if ($start) {
            $match->setStart($start);
        }
        if ($history) {
            $match->setHistory($this->unpackHistory($history));
        }

        if ($this->checkMatch($match) && $this->matchManager->save($match)) {
            return $this->prepareResponseReturn($match, $request);
        }

        throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Diese Funktion beschreibt DELETE /matches/{id}
     *
     * @param Request $request
     * @param $id
     * @return array|Hal
     *
     * @throws NotFoundHttpException wenn die Partie nicht gefunden wurde
     * @throws HttpException wenn das Objekt nicht gelöscht werden konnte
     */
    public function deleteAction(Request $request, $id)
    {
        /** @var Match $match */
        $match = $this->getMatchManager()->load($id);
        if (!$match) {
            throw new NotFoundHttpException('Could not find match ' . $id);
        }

        if ($this->getMatchManager()->delete($match)) {
            return $this->prepareResponseReturn($match, $request);
        }

        throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Diese Funktion beschreibt PATCH /matches/{id}
     *
     * @param Request $request
     * @param $id
     * @return array|Hal
     *
     * @throws NotFoundHttpException wenn die Partie nicht gefunden wurde
     * @throws HttpConflictException wenn die Partie nicht erfolgreich validiert werden konnte.
     * @throws HttpException wenn das Objekt nicht gespeichert werden konnte
     */
    public function patchAction(Request $request, $id)
    {
        /** @var Match $match */
        $match = $this->getMatchManager()->load($id);
        if (!$match) {
            throw new NotFoundHttpException('Could not find match ' . $id);
        }

        $patch = $request->request->get('PATCH');
        if (strpos($patch, 'KI') !== false) {
            sscanf($patch, 'KI %d', $millisec);
            if ($millisec) {
                $move = $this->chessService->think(
                    $match->getStart(),
                    $match->getHistory(),
                    $millisec
                );
                if ($move) {
                    if ($move === 'GAME_OVER') {
                        throw new HttpConflictException(
                            '30002',
                            'The game has already ended.'
                        );
                    }

                    $patch = $move;
                } else {
                    throw new HttpConflictException(
                        '30001',
                        'The KI could not think of an valid move.'
                    );
                }
            }
        }
        $match->addHistory($patch);

        if ($this->checkMatch($match) && $this->getMatchManager()->save($match)) {
            return $this->prepareResponseReturn($match, $request);
        }

        throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
