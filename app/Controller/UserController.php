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
     * @return array
     * @throws \Htwdd\Chessapi\Exception\NotFoundException
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
                    'ref' => 'user:'.$userId,
                    'link' => $link
                ];
            } catch (\InvalidArgumentException $e) {
            }
        }

        return $this->prepareResponseReturn($retVal, $request);
    }

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
     * @param mixed $data
     * @param Request $request
     * @return array|Hal
     */
    protected function prepareResponseReturn($data, Request $request)
    {
        if ($data instanceof User) {
            $matchArray = (new UserTransformer())->toArray($data);
            if (current($request->getAcceptableContentTypes()) === 'text/html') {
                return $matchArray;
            } else {
                return new Hal(
                    $request->getPathInfo(),
                    $matchArray
                );
            }
        }

        if (is_array($data)) {
            if (current($request->getAcceptableContentTypes()) === 'text/html') {
                $retVal = [];

                foreach ($data as $dataDetails) {
                    $retVal[] = $dataDetails['link'];
                }

                return $retVal;
            } else {
                $hal = new Hal($request->getPathInfo());
                $hal->addCurie(
                    'user',
                    // We use the generator to get us an valid URI and replace the ID with the CURIE placeholder
                    str_replace(
                        '1337',
                        '{rel}',
                        $this->getUrlGenerator()->generate('user_detail', ['id' => 1337])
                    )
                );
                foreach ($data as $dataDetails) {
                    $hal->addLink($dataDetails['ref'], $dataDetails['link']);
                }

                return $hal;
            }
        }

        return null;
    }

    /**
     * Routing Setup des User Controllers.
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
