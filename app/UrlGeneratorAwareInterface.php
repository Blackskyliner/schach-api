<?php

namespace Htwdd\Chessapi;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

interface UrlGeneratorAwareInterface
{
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator);
}
