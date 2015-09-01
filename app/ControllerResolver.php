<?php

namespace Htwdd\Chessapi;

use Symfony\Component\HttpFoundation\Request;
use Silex\ControllerResolver as BaseResolver;

/**
 * @inheritdoc
 */
class ControllerResolver extends BaseResolver
{
    /**
     * Originale Funktionsweise um eine Response und Kernel Injection erweitert.
     *
     * @param Request $request
     * @param string $controller
     * @param array $parameters
     * @return array
     */
    protected function doGetArguments(Request $request, $controller, array $parameters)
    {
        $attributes = $request->attributes->all();
        $arguments = array();
        $response = $this->app['response'];
        foreach ($parameters as $param) {
            if (array_key_exists($param->name, $attributes)) {
                $arguments[] = $attributes[$param->name];
            } elseif ($param->getClass() && $param->getClass()->isInstance($request)) {
                $arguments[] = $request;
            } elseif ($param->getClass() && $param->getClass()->isInstance($response)) {
                $arguments[] = $response;
            } elseif ($param->getClass() && $param->getClass()->isInstance($this->app['kernel'])) {
                $arguments[] = $this->app['kernel'];
            } elseif ($param->isDefaultValueAvailable()) {
                $arguments[] = $param->getDefaultValue();
            } else {
                if (is_array($controller)) {
                    $repr = sprintf('%s::%s()', get_class($controller[0]), $controller[1]);
                } elseif (is_object($controller)) {
                    $repr = get_class($controller);
                } else {
                    $repr = $controller;
                }

                throw new \RuntimeException(sprintf('Controller "%s" requires that you provide a value for the "$%s" argument (because there is no default value or because there is a non optional argument after this one).', $repr, $param->name));
            }
        }

        return $arguments;
    }
}
