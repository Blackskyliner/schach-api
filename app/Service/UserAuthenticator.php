<?php

namespace Htwdd\Chessapi\Service;

use Htwdd\Chessapi\Entity\User;
use Htwdd\Chessapi\Entity\UserManager;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UserAuthenticator
{

    /**
     * Versucht einen Benutzer zu authentifizieren.
     * @param UserManager $um
     * @param $id
     * @param $username
     * @param $password
     * @return bool
     */
    public static function authenticate(UserManager $um, $id, $username, $password)
    {
        /** @var User $user */
        $user = $um->load($id);

        // Da das Hashing in der setPassword passiert, sparen wir uns hier das hardcoden
        // des Hashing Algorithmus.
        $externalUser = new User();
        $externalUser->setName($username);
        $externalUser->setPassword($password);

        return
            $user->getName()     === $externalUser->getName() &&
            $user->getPassword() === $externalUser->getPassword();
    }

    /**
     * Kann in einer before() Action einer Controller Route verwendet werden.
     *
     * @param Request $r
     * @param Application $app
     * @return Response
     */
    public static function beforeControllerAction(Request $r, Application $app)
    {
        /** @var UserManager $userManager */
        $userManager = $app['manager.user'];

        if (!isset($_SERVER['PHP_AUTH_USER']))
        {
            header('WWW-Authenticate: Basic realm=\'Chess API\'');
            return new Response(null, Response::HTTP_UNAUTHORIZED);
        }


        if (!self::authenticate($userManager, $r->get('id', null), $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
            return new Response(null, Response::HTTP_FORBIDDEN);
        }

        return null;
    }

}
