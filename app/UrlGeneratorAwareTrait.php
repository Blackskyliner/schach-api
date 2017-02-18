<?php

namespace Htwdd\Chessapi;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Trait - Implementierung des UrlGeneratorAwareInterface
 */
trait UrlGeneratorAwareTrait
{
    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /**
     * @return UrlGeneratorInterface
     */
    protected function getUrlGenerator()
    {
        return $this->urlGenerator;
    }

    /**
     * @param UrlGeneratorInterface $urlGenerator
     */
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }
}
