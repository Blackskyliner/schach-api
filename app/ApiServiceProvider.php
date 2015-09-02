<?php

namespace Htwdd\Chessapi;

use Htwdd\Chessapi\Controller\DocumentationController;
use Htwdd\Chessapi\Controller\MatchController;
use Htwdd\Chessapi\Controller\RootController;
use Htwdd\Chessapi\Controller\UserController;
use Htwdd\Chessapi\Entity\MatchManager;
use Htwdd\Chessapi\Entity\UserManager;
use Htwdd\Chessapi\Exception\InvalidChessStateException;
use Htwdd\Chessapi\Service\AutoIncrementManager;
use Htwdd\Chessapi\Service\ChenardEngine;
use Htwdd\Chessapi\Service\ChessService;
use Htwdd\Chessapi\Service\FileManager;
use Nocarrier\HalLinkContainer;
use Silex\Application;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Diese Funktion initialisert alle von dieser API zur verfügng gestellte Dienste in einer Silex Application.
 */
class ApiServiceProvider implements ServiceProviderInterface
{
    /**
     * @inheritDoc
     */
    public function register(Application $app)
    {
        $app['resolver'] = $app->share(function () use ($app) {
            return new ControllerResolver($app, $app['logger']);
        });
        $app['response'] = new Response();
        $app['config.filemanager_directory'] = DATA_ROOT;

        $app['service.filemanager'] = $app->share(function () use ($app) {
            return new FileManager($app['config.filemanager_directory']);
        });
        $app['service.autoincrementmanager'] = $app->share(function () use ($app) {
            return new AutoIncrementManager($app['service.filemanager']);
        });
        $app['manager.user'] = $app->share(function () use ($app) {
            $manager = new UserManager(
                $app['service.filemanager'],
                $app['service.autoincrementmanager'],
                $app['request_context']
            );

            $manager->setMatchManager($app['manager.match']);

            return $manager;
        });
        $app['manager.match'] = $app->share(function () use ($app) {
            $manager = new MatchManager(
                $app['service.filemanager'],
                $app['service.autoincrementmanager'],
                $app['request_context']
            );

            return $manager;
        });

        // Registrieren aller Controller als Service
        $app['controller.match'] = $app->share(function () use ($app) {
            return new MatchController(
                $app['manager.match'],
                $app['manager.user'],
                $app['service.chess']
            );
        });
        $app['controller.user'] = $app->share(function () use ($app) {
            return new UserController($app['manager.user']);
        });
        $app['controller.root'] = $app->share(function () use ($app) {
            return new RootController();
        });
        $app['controller.documentation'] = $app->share(function () use ($app) {
            return new DocumentationController();
        });

        $app['service.chesski'] = $app->share(function () use ($app) {
            if (isset($app['chenard'], $app['chenard']['enabled']) && $app['chenard']['enabled']) {
                return new ChenardEngine(
                    isset($app['chenard']['server']) ? $app['chenard']['server'] : 'localhost',
                    isset($app['chenard']['port']) ? $app['chenard']['port'] : 12345
                );
            }

            return null;
        });

        $app['service.chess'] = $app->share(function () use ($app) {
            $chessService = new ChessService();

            if ($app['service.chesski']) {
                $chessService->setChessKi($app['service.chesski']);
            }

            return $chessService;
        });

        $app['hal_links'] = new HalLinkContainer();

        if (!isset($app['url_generator'])) {
            $app->register(new UrlGeneratorServiceProvider());
        }
        if (!isset($app['twig']) && class_exists('\Twig_Environment')) {
            $app->register(new TwigServiceProvider(), [
                'twig.path' => __DIR__.'/Resources/views',
            ]);
        }
        if (isset($app['twig'])) {
            /** @var \Twig_Environment $twig */
            $twig = $app['twig'];
            $twig->addFilter(new \Twig_SimpleFilter('statuscodeToText', function ($statusCode) {
                return isset(Response::$statusTexts[$statusCode]) ?
                    Response::$statusTexts[$statusCode] :
                    'Unknown';
            }));
            $twig->addFunction(new \Twig_SimpleFunction('url', function (
                $route,
                $params = array(),
                $type = UrlGeneratorInterface::ABSOLUTE_PATH
            ) use ($app) {
                /** @var UrlGeneratorInterface $generator */
                $generator = $app['url_generator'];
                return $generator->generate($route, $params, $type);
            }));
            $twig->addFilter(new \Twig_SimpleFilter('prettyPrint', function ($value) {
                if (is_bool($value)) {
                    return $value ? 'yes' : 'no';
                }
                return $value;
            }));
            $twig->addTest(new \Twig_SimpleTest('array', function ($value) {
                return is_array($value);
            }));
            $twig->addFilter(new \Twig_SimpleFilter('loadUser', function ($uri) use ($app) {
                /** @var UserManager $manager */
                $manager = $app['manager.user'];
                $user = $manager->loadByResource($uri);

                return $user;
            }));
            $twig->addFunction(new \Twig_SimpleFunction('getUsers', function () use ($app) {
                /** @var UserManager $userManager */
                $userManager = $app['manager.user'];
                $retVal = [];

                foreach ($userManager->listAll() as $userID) {
                    $retVal[] = $userManager->load($userID);
                }

                return $retVal;
            }));
            $twig->addFunction(new \Twig_SimpleFunction('playMatch', function ($start, array $history) use ($app) {
                /** @var ChessService $chessService */
                $chessService = $app['service.chess'];

                try {
                    return $chessService->getCurrentFen($start, $history);
                } catch (InvalidChessStateException $e) {
                    return 'Invalid history!';
                }
            }));
        }
        $app->register(new ServiceControllerServiceProvider());

        $app->mount('', new ControllerProvider());
    }

    /**
     * @inheritDoc
     */
    public function boot(Application $app)
    {
    }
}
