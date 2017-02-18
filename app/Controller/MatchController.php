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
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class MatchController
 * @package Htwdd\Chessapi\Controller
 *
 * TODO: fix history for empty lines!!!!!!!
 *
 */
class MatchController implements UrlGeneratorAwareInterface
{
    /** @var  MatchManager */
    private $matchManager;

    /** @var  UserManager */
    private $userManager;

    /** @var ChessService  */
    private $chessService;

    /** Importiere standard Getter/Setter für das RouterAwareInterface */
    use UrlGeneratorAwareTrait;

    /**
     * MatchController constructor.
     * @param MatchManager $matchManager
     */
    public function __construct(MatchManager $matchManager, UserManager $userManager, ChessService $chessService)
    {
        $this->chessService = $chessService;
        $this->matchManager = $matchManager;
        $this->userManager = $userManager;
    }

    /**
     * @return MatchManager
     */
    protected function getMatchManager()
    {
        return $this->matchManager;
    }


    /**
     * @return UserManager
     */
    protected function getUserManager()
    {
        return $this->userManager;
    }


    public function listAction(Request $request)
    {
        $retVal = [];
        foreach ($this->getMatchManager()->listAll() as $matchId) {
            try {
                $link = $this->getUrlGenerator()->generate(
                    'match_detail',
                    ['id' => $matchId]
                );

                $retVal[] = [
                    'ref' => 'match',//:'.$matchId,
                    'link' => $link
                ];
            } catch (\InvalidArgumentException $e) {
            }
        }

        return $this->prepareResponseReturn($retVal, $request);
    }

    private function checkUser($uri)
    {
        $resource = $this->userManager->loadByResource($uri);
        if ($resource === null || !$resource instanceof User) {
            throw new HttpConflictException(
                '20010',
                sprintf('The specified user "%s" could not be fetched.', $uri),
                null,
                'Make sure the given user was correctly specified and exists.'.PHP_EOL.
                'A user should be specified as an relative URI within the api. e.g. /users/1'.PHP_EOL
            );
        }

        return true;
    }

    /**
     * @param Match $match
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
                'Make sure the given history only got valid moves in the standard algebraic notation.'.PHP_EOL.
                'If you think the history is correct, there may be an error in the Chess Engine...'
            );
        }

        return true;
    }
    public function createAction(Request $request, Response $response)
    {
        $white = $request->request->get('white', null);
        $black = $request->request->get('black', null);
        $start = $request->request->get('start', null);
        $history = $request->request->get('history', []);

        if ($white) {
            $this->checkUser($white);
        }
        if ($black) {
            $this->checkUser($black);
        }

        $match = new Match();
        $match->setBlack($black);
        $match->setWhite($white);

        if ($start) {
            $match->setStart($start);
        }

        if ($history) {
            $match->setHistory(explode("\n", str_replace("\r", '', $history)));
        }

        $this->checkMatch($match);

        if ($this->getMatchManager()->save($match)) {
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

        throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function detailAction(Request $request, $id)
    {
        $match = $this->getMatchManager()->load($id);

        if ($match) {
            return $this->prepareResponseReturn($match, $request);
        }

        throw new NotFoundHttpException('Could not find match '.$id);
    }
    public function updateAction(Request $request, $id)
    {
        /** @var Match $match */
        $match = $this->matchManager->load($id);
        $white = $request->request->get('white', null);
        $black = $request->request->get('black', null);
        $start = $request->request->get('start', null);
        $history = $request->request->get('history', []);

        if (!$match) {
            throw new NotFoundHttpException('Could not find match '.$id);
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
            $match->setHistory(explode("\n", str_replace("\r", '', $history)));
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
    public function replaceAction(Request $request, $id)
    {
        /** @var Match $match */
        $match = $this->matchManager->load($id);
        $white = $request->request->get('white', null);
        $black = $request->request->get('black', null);
        $start = $request->request->get('start', null);
        $history = $request->request->get('history', []);

        if (!$match) {
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
            $match->setHistory(explode("\n", str_replace("\r", '', $history)));
        }

        if ($this->checkMatch($match) && $this->matchManager->save($match)) {
            return $this->prepareResponseReturn($match, $request);
        }

        throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function deleteAction(Request $request, $id)
    {
        /** @var Match $match */
        $match = $this->getMatchManager()->load($id);
        if (!$match) {
            throw new NotFoundHttpException('Could not find match '.$id);
        }

        if ($this->getMatchManager()->delete($match)) {
            return $this->prepareResponseReturn($match, $request);
        }

        throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function patchAction(Request $request, $id)
    {
        /** @var Match $match */
        $match = $this->getMatchManager()->load($id);
        if (!$match) {
            throw new NotFoundHttpException('Could not find match '.$id);
        }

        $patch = $request->request->get('PATCH');
        $match->addHistory($patch);

        if ($this->checkMatch($match) && $this->getMatchManager()->save($match)) {
            return $this->prepareResponseReturn($match, $request);
        }

        throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @param mixed $data
     * @param Request $request
     * @return array|Hal
     */
    protected function prepareResponseReturn($data, Request $request)
    {
        if ($data instanceof Match) {
            $matchArray = (new MatchTransformer())->toArray($data);
            if (in_array(current($request->getAcceptableContentTypes()), ['text/html', '*/*'], true)) {
                return $matchArray;
            } else {
                $hal = new Hal(
                    $request->getPathInfo(),
                    $matchArray
                );

                $this->handleEmbedding($request, $hal);

                return $hal;
            }
        }

        if (is_array($data)) {
            if (in_array(current($request->getAcceptableContentTypes()), ['text/html', '*/*'], true)) {
                $retVal = [];

                foreach ($data as $dataDetails) {
                    $retVal[] = $dataDetails['link'];
                }

                return $retVal;
            } else {
                $hal = new Hal($request->getPathInfo());
                foreach ($data as $dataDetails) {
                    $hal->addLink($dataDetails['ref'], $dataDetails['link'], array(), true);
                }

                $this->handleEmbedding($request, $hal);

                return $hal;
            }
        }

        return null;
    }

    /**
     * Fügt die Ressourcen direkt in den HAL Response hinzu.
     *
     * Könnte besser gelöst werden, indem das generisch im VIEW Event behandelt wird.
     * Dabei könnte ein SubRequest durch den HTTP Kernel an den Detailendpunkt gesendet werden.
     *
     * @param Request $request
     * @param Hal $halResource
     */
    protected function handleEmbedding(Request $request, Hal $hal) {
        $embed['resource'] = false;
        $embed['white'] = false;
        $embed['black'] = false;
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
            $embedding = [];
            foreach ($hal->getLinks() as $rel => $halLinkCollection) {
                if ($rel === 'match') {
                    foreach ($halLinkCollection as $halLink) {
                        /** @var HalLink $halLink */
                        /** @var Match $match */
                        $match = $this->getMatchManager()->loadByResource($halLink->getUri());
                        if ($match) {
                            $embedding[$halLink->getUri()] = $matchTransformer->toArray($match);
                            if ($embed['white']
                             && $match->getWhite()
                             && !array_key_exists($match->getWhite(), $embedding)
                            ) {
                                $user = $this->getUserManager()->loadByResource($match->getWhite());
                                $embedding[$match->getWhite()] = $userTransformer->toArray($user);
                            }
                            if ($embed['black']
                             && $match->getBlack()
                             && !array_key_exists($match->getBlack(), $embedding)
                            ) {
                                $user = $this->getUserManager()->loadByResource($match->getBlack());
                                $embedding[$match->getBlack()] = $userTransformer->toArray($user);
                            }
                        }
                    }
                }
            }
            if ($data = $hal->getData()) {
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
            if ($embedding) {
                $data = $hal->getData();
                $data['_embedded'] = $embedding;
                $hal->setData($data);
            }
        }
    }

    /**
     * Routing Setup des User Controllers.
     * @return array
     */
    public static function getRoutes()
    {
        $dummy = new Match();
        return [
            '/' => [
                'GET' => [
                    'method' => 'controller.match:listAction',
                    'description' => 'Get a list of matches.',
                    'returnValues' => [
                        Response::HTTP_OK => 'The response contains a list of all match resource URIs.',
                    ]
                ],
                'POST' => [
                    'method' => 'controller.match:createAction',
                    'description' => 'Create a match',
                    'content-types' => [
                        'application/x-www-form-urlencoded',
                        'application/json',
                        'text/xml',
                    ],
                    'parameters' => [
                        'white' => [
                            'required' => true,
                            'type' => 'application/user',
                            'description' => 'User who should be able to control the white figures.'
                        ],
                        'black' => [
                            'required' => true,
                            'type' => 'application/user',
                            'description' => 'User who should be able to control the black figures.'
                        ],
                        'start' => [
                            'required' => false,
                            'type' => 'text/fen',
                            'description' => 'The initial chess situation in FEN notation.',
                            'default' => $dummy->getStart(),
                        ],
                        'history' => [
                            'required' => false,
                            'type' => 'array of text/san',
                            'description' => 'Moves in chronological order which should be performed '
                                            .'on the start situation.',
                            'default' => $dummy->getHistory(),
                        ]
                    ],
                    'example' => [
                        'white' => '/users/1',
                        'black' => '/users/2',
                        'start' => $dummy->getStart(),
                        'history' => [
                            'e2-e4',
                            'f7-f6'
                        ]
                    ],
                    'returnValues' => [
                        Response::HTTP_CREATED => 'The match resource was created successfully and '.
                            'the current resource representation is contained in the response body.',
                        Response::HTTP_CONFLICT => 'The resulting match resource is not in a valid state.',
                        Response::HTTP_INTERNAL_SERVER_ERROR => 'The match resource could not '.
                            'be persisted on the server.',
                    ]
                ],
            ],
            '/{id}' => [
                'GET' =>  [
                    'method' => 'controller.match:detailAction',
                    'description' => 'Get details of an match.',
                    'returnValues' => [
                        Response::HTTP_OK => 'The match resource was found and is contained in the response body.',
                        Response::HTTP_NOT_FOUND => 'The match resource was not found.',
                    ]
                ],
                'PUT' => [
                    'method' => 'controller.match:replaceAction',
                    'content-types' => [
                        'application/x-www-form-urlencoded',
                        'application/json',
                        'text/xml',
                    ],
                    'description' => 'Replaces the match with the given content.',
                    'parameters' => [
                        'white' => [
                            'required' => false,
                            'type' => 'application/user',
                            'description' => 'User who should be able to control the white figures.'
                        ],
                        'black' => [
                            'required' => false,
                            'type' => 'application/user',
                            'description' => 'User who should be able to control the black figures.'
                        ],
                        'start' => [
                            'required' => false,
                            'type' => 'text/fen',
                            'description' => 'The initial chess situation in FEN notation.',
                            'default' => $dummy->getStart(),
                        ],
                        'history' => [
                            'required' => false,
                            'type' => 'array of text/san',
                            'description' => 'Moves in chronological order which should be performed '
                                .'on the start situation.',
                            'default' => $dummy->getHistory(),
                        ]
                    ],
                    'example' => [
                        'white' => '/users/1',
                        'black' => '/users/2',
                        'start' => $dummy->getStart(),
                        'history' => [
                            'e2-e4',
                            'f7-f6'
                        ]
                    ],
                    'returnValues' => [
                        Response::HTTP_OK => 'The match resource was replaced successfully and '.
                            'the current resource representation is contained in the response body.',
                        Response::HTTP_CONFLICT => 'The resulting match resource is not in a valid state.',
                        Response::HTTP_INTERNAL_SERVER_ERROR => 'The match resource could not '.
                            'be persisted on the server.',
                    ]
                ],
                'POST' => [
                    'method' => 'controller.match:updateAction',
                    'content-types' => [
                        'application/x-www-form-urlencoded',
                        'application/json',
                        'text/xml',
                    ],
                    'description' => 'Updates the match.',
                    'parameters' => [
                        'white' => [
                            'required' => false,
                            'type' => 'application/user',
                            'description' => 'User who should be able to control the white figures.'
                        ],
                        'black' => [
                            'required' => false,
                            'type' => 'application/user',
                            'description' => 'User who should be able to control the black figures.'
                        ],
                        'start' => [
                            'required' => false,
                            'type' => 'text/fen',
                            'description' => 'The initial chess situation in FEN notation.',
                            'default' => $dummy->getStart(),
                        ],
                        'history' => [
                            'required' => false,
                            'type' => 'array of text/san',
                            'description' => 'Moves in chronological order which should be performed '
                                .'on the start situation.',
                            'default' => $dummy->getHistory(),
                        ]
                    ],
                    'example' => [
                        'white' => '/users/1',
                        'black' => '/users/2',
                        'start' => $dummy->getStart(),
                        'history' => [
                            'e2-e4',
                            'f7-f6'
                        ]
                    ],
                    'returnValues' => [
                        Response::HTTP_OK => 'The match resource was updated successfully and '.
                            'the current resource representation is contained in the response body.',
                        Response::HTTP_NOT_FOUND => 'The match resource was not found.',
                        Response::HTTP_CONFLICT => 'The resulting match resource is not in a valid state.',
                        Response::HTTP_INTERNAL_SERVER_ERROR => 'The match resource could not '.
                            'be persisted on the server.',
                    ]
                ],
                'PATCH' => [
                    'method' => 'controller.match:patchAction',
                    'content-types' => [
                        'text/san',
                    ],
                    'description' => 'Add a move in standard algebraic notation to the current history of moves.',
                    'example' => 'e4',
                    'returnValues' => [
                        Response::HTTP_OK => 'The match resource was updated successfully and '.
                            'the current resource representation is contained in the response body.',
                        Response::HTTP_NOT_FOUND => 'The match resource was not found.',
                        Response::HTTP_CONFLICT => 'The resulting match resource is not in a valid state.',
                        Response::HTTP_INTERNAL_SERVER_ERROR => 'The match resource could not '.
                            'be persisted on the server.',
                    ]
                ],
                'DELETE' => [
                    'method' => 'controller.match:deleteAction',
                    'description' => 'Delete the match.',
                    'returnValues' => [
                        Response::HTTP_OK => 'The match resource was deleted successfully and '.
                            'the last known resource representation is contained in the response body.',
                        Response::HTTP_NOT_FOUND => 'The match resource was not found.',
                        Response::HTTP_INTERNAL_SERVER_ERROR => 'The match resource could not '.
                            'be deleted from the server.',
                    ]
                ],
            ]
        ];
    }
}
