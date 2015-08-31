<?php

namespace Htwdd\Chessapi;

use Htwdd\Chessapi\Controller\MatchController;
use Htwdd\Chessapi\Controller\RootController;
use Htwdd\Chessapi\Controller\UserController;
use Htwdd\Chessapi\Exception\HttpConflictException;
use Nocarrier\Hal;
use Nocarrier\HalLink;
use Nocarrier\HalLinkContainer;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;

use Silex\Route;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Diese Klasse erstellt das Routing in der Applikation für die Controller der chessApi.
 *
 * @package Htwdd\Chessapi
 */
class ControllerProvider implements ControllerProviderInterface
{
    /**
     * @param Application $app
     * @param ControllerCollection $rootCollection
     * @param $routeDefinitions
     * @param $routePrefix
     * @return ControllerCollection
     */
    protected function registerController(
        Application $app,
        ControllerCollection $rootCollection,
        $routeDefinitions,
        $routePrefix = ''
    ) {
        /** @var ControllerCollection $controllerCollection */
        $controllerCollection = $app['controllers_factory'];

        foreach ($routeDefinitions as $path => $methodsWithDetails) {
            $optionsArray = [];
            $hasPatch = array_key_exists('PATCH', $methodsWithDetails);

            foreach ($methodsWithDetails as $method => $routeDetails) {
                $controller = $controllerCollection->match($path, $routeDetails['method']);
                $controller->method($method);

                // einfache routen namen erstellen
                $controller->bind(
                    str_replace(
                        array('controller.', ':', '.', 'Action'),
                        array('', '_', '_', ''),
                        $routeDetails['method']
                    )
                );

                if (array_key_exists('before', $routeDetails) && is_callable($routeDetails['before'])) {
                    $controller->before($routeDetails['before']);
                }
                if (array_key_exists('after', $routeDetails) && is_callable($routeDetails['after'])) {
                    $controller->after($routeDetails['before']);
                }

                if (array_key_exists('convert', $routeDetails) && is_array($routeDetails['convert'])) {
                    foreach ($routeDetails['convert'] as $pattern => $callable) {
                        $controller->convert($pattern, $callable);
                    }
                }

                foreach (['description', 'parameters', 'example', 'returnValues'] as $docField) {
                    if (array_key_exists($docField, $routeDetails)) {
                        $optionsArray[$method][$docField] = $routeDetails[$docField];
                    }
                }

                // Accept-Patch Header
                if ($hasPatch && array_key_exists('content-types', $methodsWithDetails['PATCH'])) {
                    $controller->after(function (Request $request, Response $response) use ($methodsWithDetails) {
                        $response->headers->set(
                            'Accept-Patch',
                            implode(',', $methodsWithDetails['PATCH']['content-types'])
                        );
                    });
                    if ($method === 'PATCH') {
                        $controller->before(function (Request $request) {
                            $request->request->set('PATCH', file_get_contents('php://input'));
                        });
                    }
                }

                $controller->value('_routeDescription', $routeDetails);
            }

            // Register the OPTIONS handler.
            $controller = $controllerCollection->match($path, 'documentation:optionsAction');
            $controller->method('OPTIONS');
            $controller->value('_documentation', $optionsArray);
        }

        $rootCollection->mount($routePrefix, $controllerCollection);

        return $controllerCollection;
    }

    public function connect(Application $app)
    {
        /** @var ControllerCollection $controllers */
        $controllers = $app['controllers_factory'];

        $this->registerController($app, $controllers, RootController::getRoutes(), '');
        $this->registerController($app, $controllers, UserController::getRoutes(), '/users');
        $this->registerController($app, $controllers, MatchController::getRoutes(), '/matches');

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $app['dispatcher'];
        // HTTP 415 and HAL handling
        $dispatcher->addListener(
            KernelEvents::CONTROLLER,
            function (FilterControllerEvent $event) use ($app, $controllers) {
                if (!$event->isMasterRequest()) {
                    return;
                }

                /** @var RouteCollection $routes */
                /** @var UrlMatcherInterface $urlMatcher */
                $urlMatcher = $app['url_matcher'];
                $routes = $app['routes'];
                $request = $event->getRequest();
                // HTTP 415 Handling, if content-types are defined for the current route
                $allowedContentTypes = $request->get('_routeDescription');
                $currentContentType = $request->headers->get('content-type');
                $currentContentType = explode(';', $currentContentType);
                $currentContentType = trim($currentContentType[0]);
                if ($allowedContentTypes
                    && array_key_exists('content-types', $allowedContentTypes)
                    && !in_array($currentContentType, $allowedContentTypes['content-types'], true)
                ) {
                    $event->setController(function () {
                        return new Response(null, Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
                    });
                }

                // Only create HAL Links if we got a matching route
                // Otherwise the route-path lookup would fail.
                $currentRoute = $urlMatcher->match($request->getPathInfo());
                $currentRoute = $routes->get($currentRoute['_route']);

                if ($currentRoute === null) {
                    return;
                }

                /** @var HalLinkContainer $halLinks */
                $halLinks = $app['hal_links'];
                $currentDepth = substr_count(rtrim($currentRoute->getPath(), '/'), '/');
                foreach ($routes as $routeName => $routeObject) {
                    /** @var Route $routeObject */
                    $routeDepth = substr_count(rtrim($routeObject->getPath(), '/'), '/');
                    if ((( // Test for sub-links
                                $routeDepth > $currentDepth // only links in deeper depth
                                && $routeDepth - $currentDepth === 1 // constrain depth to 1
                                && strpos(
                                    $routeObject->getPath(),
                                    $currentRoute->getPath()
                                ) === 0 // only routes which start with current URI
                            ) || ( // Test for parent links
                                $routeDepth < $currentDepth
                                && $routeDepth - $currentDepth === -1
                                && strpos($currentRoute->getPath(), $routeObject->getPath()) === 0
                            )) && in_array('GET', $routeObject->getMethods(), true) // we only want to expose GET uris
                    ) {
                        $attribs = ['rel' => $routeName];
                        if (strpos($routeObject->getPath(), '{')) {
                            $attribs['templated'] = true;
                        }
                        $halLinks->append(
                            new HalLink($routeObject->getPath(), $attribs)
                        );
                    }
                }
                $app['hal_links'] = $halLinks;
            }
        );

        // Content-Negotiation and Twig Rendering of array returns
        $dispatcher->addListener(KernelEvents::VIEW, function (GetResponseForControllerResultEvent $event) use ($app) {
            if (is_array($event->getControllerResult())) {
                $acceptHeaders = AcceptHeader::fromString($event->getRequest()->headers->get('Accept'));
                $data = $event->getControllerResult();

                /** @var Response $response */
                $response = $app['response'];

                foreach ($acceptHeaders->all() as $header) {
                    switch ($header->getValue()) {
                        case 'text/html':
                        case '*/*':
                            if (isset($app['twig'])) {
                                /** @var \Twig_Environment $twig */
                                $twig = $app['twig'];
                                $twig->addGlobal('request', $app['request']);

                                $controller = $event->getRequest()->get('_controller');
                                $viewPath = strtr($controller, array(
                                    'controller.'   => '',
                                    'Controller'    => '',
                                    'Action'        => '',
                                    '::'            => '/',
                                    ':'            => '/',
                                    '\\'            => '/',
                                ));

                                if (strpos($viewPath, '/') !== false) {
                                    $viewPath = ucfirst($viewPath);
                                }
                                $viewPath .= '.html.twig';

                                $response->setContent(
                                    $twig->render(
                                        $viewPath,
                                        [
                                            'data' => $data,
                                            'links' => $app['hal_links']
                                        ]
                                    )
                                );
                                $event->setResponse($response);
                            }
                            break;
                        case 'application/json':
                            $jsonResponse = new JsonResponse($data);

                            $response->headers->add($jsonResponse->headers->all());
                            $response->setContent($jsonResponse->getContent());

                            $event->setResponse($response);
                            break;
                        case 'text/xml':
                            $xmlResponse = new \SimpleXMLElement("<?xml version=\"1.0\"?><response></response>");

                            $this->arrayToXml($data, $xmlResponse);
                            $response->headers->set('Content-Type', 'text/xml');
                            $response->setContent($xmlResponse->asXML());

                            $event->setResponse($response);
                            break;
                        default:
                            $response->setStatusCode(Response::HTTP_NOT_ACCEPTABLE);
                            $event->setResponse($response);
                    }
                }
            }
            if ($event->getControllerResult() instanceof Hal) {
                $acceptHeaders = AcceptHeader::fromString($event->getRequest()->headers->get('Accept'));

                /** @var Hal $data */
                $data = $event->getControllerResult();

                /** @var HalLinkContainer $halLinks */
                $halLinks = $app['hal_links'];

                // merge global links
                foreach ($halLinks as $link) {
                    /** @var HalLink $link */
                    $attribs = $link->getAttributes();
                    $uri = $link->getUri();
                    $rel = $attribs['rel'];
                    unset($attribs['rel']);

                    $data->addLink($rel, $uri, $attribs);
                }

                /** @var Response $response */
                $response = $app['response'];
                foreach ($acceptHeaders->all() as $header) {
                    switch ($header->getValue()) {
                        case 'application/json':
                            $jsonResponse = new JsonResponse($data->asJson(false, false));

                            $response->headers->add($jsonResponse->headers->all());
                            $response->setContent($jsonResponse->getContent());

                            $event->setResponse($response);
                            break;
                        case 'text/xml':
                            // handle _embedded otherwise it won't be XML valid...
                            $halData = $data->getData();
                            if (array_key_exists('_embedded', $halData)) {
                                foreach ($halData['_embedded'] as $uri => $resource) {
                                    $data->addResource('embedded', new Hal(
                                        $uri,
                                        $resource
                                    ));
                                }
                            }
                            unset($halData['_embedded']);
                            $data->setData($halData);

                            $response->headers->set('Content-Type', 'text/xml');
                            $response->setContent($data->asXml(false));

                            $event->setResponse($response);
                            break;
                        default:
                            $response->setStatusCode(Response::HTTP_NOT_ACCEPTABLE);
                            $event->setResponse($response);
                    }
                }
            }
        });

        // Handle Exceptions gracefully
        $dispatcher->addListener(KernelEvents::EXCEPTION, function (GetResponseForExceptionEvent $event) use ($app) {
            $exception = $event->getException();
            if ($exception instanceof HttpConflictException) {
                $acceptHeaders = AcceptHeader::fromString($event->getRequest()->headers->get('Accept'));

                $data = [
                    'exception' => $exception
                ];

                /** @var Response $response */
                $response = $app['response'];
                $response->setStatusCode($exception->getStatusCode());

                foreach ($acceptHeaders->all() as $header) {
                    switch ($header->getValue()) {
                        case 'text/html':
                            if (isset($app['twig'])) {
                                /** @var \Twig_Environment $twig */
                                $twig = $app['twig'];
                                $response->setContent(
                                    $twig->render(
                                        'exception.html.twig',
                                        $data
                                    )
                                );
                                $event->setResponse($response);
                            }
                            return;
                        case 'application/json':
                            $jsonResponse = new JsonResponse($exception->getConflicInformation());

                            $response->headers->add($jsonResponse->headers->all());
                            $response->setContent($jsonResponse->getContent());

                            $event->setResponse($response);
                            return;
                        case 'text/xml':
                            $xmlResponse = new \SimpleXMLElement("<?xml version=\"1.0\"?><response></response>");

                            $this->arrayToXml($exception->getConflicInformation(), $xmlResponse);
                            $response->headers->set('Content-Type', 'text/xml');
                            $response->setContent($xmlResponse->asXML());

                            $event->setResponse($response);
                            return;
                        default:
                            $response->setStatusCode(Response::HTTP_NOT_ACCEPTABLE);
                            $event->setResponse($response);
                    }
                }
            }
            if ($exception instanceof HttpException) {
                $acceptHeaders = AcceptHeader::fromString($event->getRequest()->headers->get('Accept'));

                $data = [
                    'exception' => $exception
                ];

                /** @var Response $response */
                $response = $app['response'];
                $response->setStatusCode($exception->getStatusCode());
                foreach ($acceptHeaders->all() as $header) {
                    switch ($header->getValue()) {
                        case 'text/html':
                            if (isset($app['twig'])) {
                                /** @var \Twig_Environment $twig */
                                $twig = $app['twig'];
                                $response->setContent(
                                    $twig->render(
                                        'exception.html.twig',
                                        $data
                                    )
                                );
                                $event->setResponse($response);
                            }
                            return;
                        case 'application/json':
                            $jsonResponse = new JsonResponse($exception->getMessage());

                            $response->headers->add($jsonResponse->headers->all());
                            $response->setContent($jsonResponse->getContent());

                            $event->setResponse($response);
                            return;
                        case 'text/xml':
                            $xmlResponse = new \SimpleXMLElement("<?xml version=\"1.0\"?><response></response>");

                            $message = $exception->getMessage();
                            if (!is_array($message)) {
                                $xmlResponse = new \SimpleXMLElement(
                                    "<?xml version=\"1.0\"?><response>$message</response>"
                                );
                            } else {
                                $this->arrayToXml($message, $xmlResponse);
                            }

                            $response->headers->set('Content-Type', 'text/xml');
                            $response->setContent($xmlResponse->asXML());

                            $event->setResponse($response);

                        default:
                            $response->setStatusCode(Response::HTTP_NOT_ACCEPTABLE);
                            $event->setResponse($response);
                    }
                }
            }
        });

        // Inject UrlGenerator into Controllers
        $dispatcher->addListener(KernelEvents::CONTROLLER, function (FilterControllerEvent $event) use ($app) {
            /** @var array $controller */
            $controller = $event->getController();
            if (is_array($controller) && $controller[0] instanceof UrlGeneratorAwareInterface) {
                $controller[0]->setUrlGenerator($app['url_generator']);
            }
        });

        // Inject Accept Header into Responses
        $dispatcher->addListener(KernelEvents::RESPONSE, function (FilterResponseEvent $event) {
            $routeDescription = $event->getRequest()->get('_routeDescription', []);
            if (array_key_exists('content-types', $routeDescription)) {
                $event->getResponse()->headers->add([
                    'Accept' => implode(', ', $routeDescription['content-types'])
                ]);
            }
        });

        return $controllers;
    }

    /**
     * @param array $array
     * @param \SimpleXMLElement $xmlUserInfo
     * Taken from: @see http://www.codexworld.com/convert-array-to-xml-in-php/
     */
    private function arrayToXml($array, &$xmlUserInfo)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (!is_numeric($key)) {
                    $subNode = $xmlUserInfo->addChild($key);
                    $this->arrayToXml($value, $subNode);
                } else {
                    $subNode = $xmlUserInfo->addChild('item'.$key);
                    $this->arrayToXml($value, $subNode);
                }
            } else {
                $xmlUserInfo->addChild($key, htmlspecialchars($value));
            }
        }
    }
}
