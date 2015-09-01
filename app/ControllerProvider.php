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
 * Zudem werden spezielle Funktionen der API in dieser Klasse umgesetzt:
 *      - Accept Header
 *      - Allow-Patch Header
 *      - Content-Negotiation
 *      - OPTIONS Dokumentation durch Controller::getRoutes Definitionen.
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
            /**
             * Dieses Array beinhaltet die Dokumentation aller Methoden des aktuellen $path.
             *
             * @var array $optionsArray
             */
            $optionsArray = [];

            // Wenn die PATCH Methode für diesen Enpunkt definiert wurde speichern wir dies zwischen,
            // damit alle für alle Methoden der Allow-Patch Header definiert werden kann
            $hasPatch = array_key_exists('PATCH', $methodsWithDetails);

            // Diese Schleife Registriert die einzelnen Routen in Silex
            // $method entspricht dabei GET, PUT, ...
            // Die Routedetails dem Array der Methode aus den jeweiligen Controller::getRoutes() Funktionen
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

                // Diese Felder werden für die Dokumentation betrachtet.
                foreach (['description', 'parameters', 'example', 'returnValues'] as $docField) {
                    if (array_key_exists($docField, $routeDetails)) {
                        // Wenn das jeweilige Feld existiert wird es dem OPTIONS Array hinzugefügt
                        $optionsArray[$method][$docField] = $routeDetails[$docField];
                    }
                }

                // Accept-Patch Header hinzufügen
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

                // @todo: Vereinfacht nur den Zugriff für die content-negotiation
                //        könnte durch eigene Felder, ähnlich der _documentation abgebildet werden.
                $controller->value('_routeDescription', $routeDetails);
            }

            // Hier registrieren wir die OPTIONS Methode für den jeweiligen Routenendpunkt
            // Dabei wird das $optionsArray mit der gesamten Dokumentation aller Endpunkte übergeben.
            // Die verarbeitende Methode DocumentationController::optionsAction kann diese dann
            // aus dem Request durch ->get('_documentation') lesen
            $controller = $controllerCollection->match($path, 'controller.documentation:optionsAction');
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
        // HTTP 415 (Content-Negotiation) Serverside
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

                // Hier wird geprüft, ob der vom Client übergebene Content-Type für die aktuelle Route untestüzt wird.
                // Ist dies nicht der Fall, so wird ein 415 Response an den Clienten gesendet.
                $allowedContentTypes = $request->get('_routeDescription');
                $currentContentType = $request->headers->get('content-type');
                $currentContentType = explode(';', $currentContentType);
                $currentContentType = trim($currentContentType[0]);
                if ($allowedContentTypes && array_key_exists('content-types', $allowedContentTypes)) {
                    if (!in_array($currentContentType, $allowedContentTypes['content-types'], true)
                    ) {
                        $event->setController(function () {
                            return new Response(null, Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
                        });
                    } else {
                        switch ($currentContentType) { // Content-Negotiation Serverseite
                            case 'application/x-www-form-urlencoded':
                                // Bereits durch Request geprast.
                                break;
                            case 'application/json':
                                $json = file_get_contents('php://input');
                                $json = json_decode($json, true);
                                if (json_last_error() !== JSON_ERROR_NONE) {
                                    $event->setController(function () {
                                        throw new HttpConflictException(
                                            '90001',
                                            'The specified JSON was invalid and could not be parsed'
                                        );
                                    });
                                }

                                $request->request->add($json);
                                break;
                            case 'text/xml':
                                $xml = file_get_contents('php://input');
                                $request->request->add(current($this->xml_to_array($xml, false)));
                                break;
                        }
                    }
                }


                /*
                 * Die folgende Sektion kpmmert sich um das automatische einbetten
                 * der URIs der jeweils adjazenten Ebene. (HATEOAS)
                 */
                $currentRoute = $urlMatcher->match($request->getPathInfo());
                $currentRoute = $routes->get($currentRoute['_route']);

                if ($currentRoute === null) {
                    // Wenn die aktuelle Route Unbekannt ist, können wir nicht mehr die Tiefe bestimmen,
                    // da wir dafür die hinter einer Request-URI liegenden Route benötigen um an die
                    // parametrisierte URI zu gelangen und unsere Position innerhalb der API zu bestimmen.
                    return;
                }


                // Wir holen uns den globalen Container für Links
                /** @var HalLinkContainer $halLinks */
                $halLinks = $app['hal_links'];

                // Danach bestimmen wir die Tiefe der aktuellen Route
                $currentDepth = substr_count(rtrim($currentRoute->getPath(), '/'), '/');

                // Jetzt iterieren wir über alle Routen um nach adjazenten Routen zu suchen.
                foreach ($routes as $routeName => $routeObject) {
                    /** @var Route $routeObject */
                    // Dabei bestimmen wir die Tiefe der Route
                    $routeDepth = substr_count(rtrim($routeObject->getPath(), '/'), '/');
                    if ((( // Test für Ebene +1 Links
                                $routeDepth > $currentDepth // Nur Links die tiefer liegen
                                && $routeDepth - $currentDepth === 1 // Und einen Abstand von 1 besitzen
                                && strpos(
                                    $routeObject->getPath(),
                                    $currentRoute->getPath()
                                ) === 0 // Zudem müssen die Routen mit unserer aktuellen Route beginnen
                            ) || ( // Test für Ebene -1 Links
                                $routeDepth < $currentDepth // Nur Links die in einer höheren Ebene liegen
                                && $routeDepth - $currentDepth === -1 // Und einen Abstand von 1 besitzen
                                && strpos(
                                    $currentRoute->getPath(),
                                    $routeObject->getPath()
                                ) === 0 // Zudem muss unsere Aktuelle Route diese enthalten
                            )) && in_array('GET', $routeObject->getMethods(), true) // außerdem muss es eine GET Route sein
                    ) {
                        // Die aktuelle Route ist entweder - oder + 1 ebene und eine GET Methode

                        // als Relation innerhalb der API verwenden wir einfach den Routenamen
                        $attribs = ['rel' => $routeName];

                        // Wenn ein Parameter im Pfad der Route gefunden wird, weisen wir darauf hin,
                        // dass die Route nur ein Template darstellt und die Parameter vor der verwendung ersetzt
                        // werden müssen.
                        if (strpos($routeObject->getPath(), '{')) {
                            $attribs['templated'] = true;
                        }

                        // Danach fügen wir diese Route als Link zu unserem globalen Link Container hinzu.
                        $halLinks->append(
                            new HalLink($routeObject->getPath(), $attribs)
                        );
                    }
                }
            }
        );

        // Content-Negotiation and Twig Rendering of array returns
        $dispatcher->addListener(KernelEvents::VIEW, function (GetResponseForControllerResultEvent $event) use ($app) {
            if (is_array($event->getControllerResult())) {
                // Dieser Pfad wird abgearbeitet, wenn die Rückgabe eines Controllers ein normales PHP Array ist.
                // Momentan wird der Pfad nur für das Twig renderign verwendet,
                // da die Controller durch prepareResponse eine entsprechende Bedingung haben
                // und nur dann ein Array zurückgeben.

                $acceptHeaders = AcceptHeader::fromString($event->getRequest()->headers->get('Accept'));
                $data = $event->getControllerResult();

                /** @var Response $response */
                $response = $app['response'];

                foreach ($acceptHeaders->all() as $header) {
                    switch ($header->getValue()) { // Content-Negotiation Client
                        case 'text/html':
                        case '*/*':
                            if (isset($app['twig'])) {
                                /** @var \Twig_Environment $twig */
                                $twig = $app['twig'];
                                $twig->addGlobal('request', $app['request']);

                                // Hier erstellen wir den Pfad für einen View aus dem Controllernamen und der Aktion.
                                // UserController::createAction oder controller.createAction
                                // Resutieren dabei in User/create.html.twig
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

                                // Dieses Template wird dann zum Rendern verwendet
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
                            return;
                        case 'application/json':
                            // Die Daten, die der Controller zurückgegeben hat,
                            // werden als JSON umgesetzt und zurückgegeben.
                            $jsonResponse = new JsonResponse($data);

                            $response->headers->add($jsonResponse->headers->all());
                            $response->setContent($jsonResponse->getContent());

                            $event->setResponse($response);
                            return;
                        case 'text/xml':
                            // Die Daten, die der Controller zurückgegeben hat,
                            // werden als XML umgesetzt und zurückgegeben.
                            $xmlResponse = new \SimpleXMLElement("<?xml version=\"1.0\"?><response></response>");

                            $this->arrayToXml($data, $xmlResponse);
                            $response->headers->set('Content-Type', 'text/xml');
                            $response->setContent($xmlResponse->asXML());

                            $event->setResponse($response);
                            return;
                        default:
                            // Unbekanntes Format => 406
                            $response->setStatusCode(Response::HTTP_NOT_ACCEPTABLE);
                            $event->setResponse($response);
                    }
                }
            }
            if ($event->getControllerResult() instanceof Hal) {
                // Wird vom Controller ein Hal Objekt zurückgegeben, dann wird dieser Pfad abgearbeitet.
                $acceptHeaders = AcceptHeader::fromString($event->getRequest()->headers->get('Accept'));

                /** @var Hal $data */
                $data = $event->getControllerResult();

                /** @var HalLinkContainer $halLinks */
                $halLinks = $app['hal_links'];

                // Globale Links in die Halinstanz hinzufügen.
                // z.B. die Verweise auf höher und tiefer ligende Ebenen.
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
                    switch ($header->getValue()) { // Content-Negotiation Client
                        case 'application/json':
                            // Rendern des HAL Objektes als JSON
                            $jsonResponse = new JsonResponse($data->asJson(false, false));

                            $response->headers->add($jsonResponse->headers->all());
                            $response->setContent($jsonResponse->getContent());

                            $event->setResponse($response);
                            return;
                        case 'text/xml':
                            // _embedded wird in der XML Darstellung ein wenig anders dargestellt
                            // Dies liegt daran, dass das generierte XML sonst nicht gültig sein könnte
                            // Zudem vereinfacht es den Zugriff auf die embedded Felder
                            // (Und XML ist so ja schon anstrengend genug, da kann man es nur einfacher machen wo möglich)
                            $halData = $data->getData();
                            if (array_key_exists('_embedded', $halData)) {
                                foreach ($halData['_embedded'] as $uri => $resource) {
                                    $data->addResource('embedded', new Hal(
                                        $uri,
                                        $resource
                                    ));
                                }
                            }

                            // Danach entfernen wir _embedded aus dem Datenfeld,
                            // da es von HAL jetzt als eigene Ressource verwaltet wird.
                            unset($halData['_embedded']);
                            $data->setData($halData);

                            // Danach bauen wir den Response
                            $response->headers->set('Content-Type', 'text/xml');
                            $response->setContent($data->asXml(false));

                            $event->setResponse($response);
                            return;
                        default:
                            // Unbekanntes Format => 406
                            $response->setStatusCode(Response::HTTP_NOT_ACCEPTABLE);
                            $event->setResponse($response);
                    }
                }
            }
        });

        // Bei einer Exception wollen wir nicht zwangsläufig immer einen 500er haben...
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

                foreach ($acceptHeaders->all() as $header) { // Content-Negotiation Client
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
                foreach ($acceptHeaders->all() as $header) { // Content-Negotiation Client
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
                            return;
                        default:
                            $response->setStatusCode(Response::HTTP_NOT_ACCEPTABLE);
                            $event->setResponse($response);
                    }
                }
            }
        });

        // Injectet den UrlGenerator in Controller die das UrlGeneratorAwareInterface implementieren
        $dispatcher->addListener(KernelEvents::CONTROLLER, function (FilterControllerEvent $event) use ($app) {
            /** @var array $controller */
            $controller = $event->getController();
            if (is_array($controller) && $controller[0] instanceof UrlGeneratorAwareInterface) {
                $controller[0]->setUrlGenerator($app['url_generator']);
            }
        });

        // Inject Accept Header in Responses
        $dispatcher->addListener(KernelEvents::RESPONSE, function (FilterResponseEvent $event) {
            // Wenn ein Content-Type in der Reoutedescription definiert wurde,
            // so wird der Accept Header hinzugefügt, damit der Client weiß was er senden darf und was nicht.

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
     * arrayToXml aus dem HalXmlRenderer entnommen
     *
     * @param array $data
     * @param \SimpleXmlElement $element
     * @param mixed $parent
     * @access protected
     * @return void
     */
    protected function arrayToXml($data, \SimpleXmlElement $element, $parent = null)
    {
        foreach ($data as $key => $value) {
            if (is_array($value) || $value instanceof \Traversable) {
                if (!is_numeric($key)) {
                    if (count($value) > 0 && isset($value[0])) {
                        $this->arrayToXml($value, $element, $key);
                    } else {
                        $subnode = $element->addChild($key);
                        $this->arrayToXml($value, $subnode, $key);
                    }
                } else {
                    $subnode = $element->addChild($parent);
                    $this->arrayToXml($value, $subnode, $parent);
                }
            } else {
                if (!is_numeric($key)) {
                    if (substr($key, 0, 1) === '@') {
                        $element->addAttribute(substr($key, 1), $value);
                    } elseif ($key === 'value' and count($data) === 1) {
                        $element[0] = $value;
                    } elseif (is_bool($value)) {
                        $element->addChild($key, intval($value));
                    } else {
                        $element->addChild($key, htmlspecialchars($value, ENT_QUOTES));
                    }
                } else {
                    $child = $element->addChild($parent, htmlspecialchars($value, ENT_QUOTES));
                    $child->addAttribute('number', $key);
                }
            }
        }
    }

    /**
     * Parsing XML into array.
     *
     * @param string $contents string containing XML
     * @param bool $getAttributes
     * @param bool $tagPriority priority of values in the array - `true` if the higher priority in the tag, `false` if only the attributes needed
     * @param string $encoding target XML encoding
     * @return array
     *
     * @link https://github.com/P54l0m5h1k/XML-to-Array-PHP/
     */
    protected function xml_to_array($contents, $getAttributes = true, $tagPriority = true, $encoding = 'utf-8')
    {
        $contents = trim($contents);
        if (empty ($contents)) {
            return [];
        }
        $parser = xml_parser_create('');
        xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, $encoding);
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        if (xml_parse_into_struct($parser, $contents, $xmlValues) === 0) {
            xml_parser_free($parser);
            return [];
        }
        xml_parser_free($parser);
        if (empty($xmlValues)) {
            return [];
        }
        unset($contents, $parser);
        $xmlArray = [];
        $current = &$xmlArray;
        $repeatedTagIndex = [];
        foreach ($xmlValues as $num => $xmlTag) {
            $result = null;
            $attributesData = null;
            if (isset ($xmlTag['value'])) {
                if ($tagPriority) {
                    $result = $xmlTag['value'];
                } else {
                    $result['value'] = $xmlTag['value'];
                }
            }
            if (isset ($xmlTag['attributes']) and $getAttributes) {
                foreach ($xmlTag['attributes'] as $attr => $val) {
                    if ($tagPriority) {
                        $attributesData[$attr] = $val;
                    } else {
                        $result['@attributes'][$attr] = $val;
                    }
                }
            }
            if ($xmlTag['type'] == 'open') {
                $parent[$xmlTag['level'] - 1] = &$current;
                if (!is_array($current) or (!in_array($xmlTag['tag'], array_keys($current)))) {
                    $current[$xmlTag['tag']] = $result;
                    unset($result);
                    if ($attributesData) {
                        $current['@'.$xmlTag['tag']] = $attributesData;
                    }
                    $repeatedTagIndex[$xmlTag['tag'].'_'.$xmlTag['level']] = 1;
                    $current = &$current[$xmlTag['tag']];
                } else {
                    if (isset ($current[$xmlTag['tag']]['0'])) {
                        $current[$xmlTag['tag']][$repeatedTagIndex[$xmlTag['tag'].'_'.$xmlTag['level']]] = $result;
                        unset($result);
                        if ($attributesData) {
                            if (isset ($repeatedTagIndex['@'.$xmlTag['tag'].'_'.$xmlTag['level']])) {
                                $current[$xmlTag['tag']][$repeatedTagIndex['@'.$xmlTag['tag'].'_'.$xmlTag['level']]] = $attributesData;
                            }
                        }
                        $repeatedTagIndex[$xmlTag['tag'].'_'.$xmlTag['level']] += 1;
                    } else {
                        $current[$xmlTag['tag']] = [$current[$xmlTag['tag']], $result];
                        unset($result);
                        $repeatedTagIndex[$xmlTag['tag'].'_'.$xmlTag['level']] = 2;
                        if (isset ($current['@'.$xmlTag['tag']])) {
                            $current[$xmlTag['tag']]['@0'] = $current['@'.$xmlTag['tag']];
                            unset ($current['@'.$xmlTag['tag']]);
                        }
                        if ($attributesData) {
                            $current[$xmlTag['tag']]['@1'] = $attributesData;
                        }
                    }
                    $lastItemIndex = $repeatedTagIndex[$xmlTag['tag'].'_'.$xmlTag['level']] - 1;
                    $current = &$current[$xmlTag['tag']][$lastItemIndex];
                }
            } elseif ($xmlTag['type'] == 'complete') {
                if (!isset ($current[$xmlTag['tag']]) and empty ($current['@'.$xmlTag['tag']])) {
                    $current[$xmlTag['tag']] = $result;
                    unset($result);
                    $repeatedTagIndex[$xmlTag['tag'].'_'.$xmlTag['level']] = 1;
                    if ($tagPriority and $attributesData) {
                        $current['@'.$xmlTag['tag']] = $attributesData;
                    }
                } else {
                    if (isset ($current[$xmlTag['tag']]['0']) and is_array($current[$xmlTag['tag']])) {
                        $current[$xmlTag['tag']][$repeatedTagIndex[$xmlTag['tag'].'_'.$xmlTag['level']]] = $result;
                        unset($result);
                        if ($tagPriority and $getAttributes and $attributesData) {
                            $current[$xmlTag['tag']]['@'.$repeatedTagIndex[$xmlTag['tag'].'_'.$xmlTag['level']]] = $attributesData;
                        }
                        $repeatedTagIndex[$xmlTag['tag'].'_'.$xmlTag['level']] += 1;
                    } else {
                        $current[$xmlTag['tag']] = array(
                            $current[$xmlTag['tag']],
                            $result
                        );
                        unset($result);
                        $repeatedTagIndex[$xmlTag['tag'].'_'.$xmlTag['level']] = 1;
                        if ($tagPriority and $getAttributes) {
                            if (isset ($current['@'.$xmlTag['tag']])) {
                                $current[$xmlTag['tag']]['@0'] = $current['@'.$xmlTag['tag']];
                                unset ($current['@'.$xmlTag['tag']]);
                            }
                            if ($attributesData) {
                                $current[$xmlTag['tag']]['@'.$repeatedTagIndex[$xmlTag['tag'].'_'.$xmlTag['level']]] = $attributesData;
                            }
                        }
                        $repeatedTagIndex[$xmlTag['tag'].'_'.$xmlTag['level']] += 1;
                    }
                }
            } elseif ($xmlTag['type'] == 'close') {
                $current = &$parent[$xmlTag['level'] - 1];
            }
            unset($xmlValues[$num]);
        }
        return $xmlArray;
    }
}
