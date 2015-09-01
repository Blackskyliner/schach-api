<?php

namespace Htwdd\Chessapi;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Klassen, die dieses Interface implementieren können einen UrlGenerator Injectet bekommen.
 */
interface UrlGeneratorAwareInterface
{
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator);
}
