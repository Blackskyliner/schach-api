<?php

namespace Htwdd\Chessapi\DataTransformer;

use Htwdd\Chessapi\Entity\Match;
use Htwdd\Chessapi\UrlGeneratorAwareInterface;
use Htwdd\Chessapi\UrlGeneratorAwareTrait;

class MatchTransformer implements UrlGeneratorAwareInterface
{
    use UrlGeneratorAwareTrait;

    public function toArray(Match $match)
    {
        return [
            'id' => $match->getId(),
            'white' => $match->getWhite(),
            'black' => $match->getBlack(),
            'start' => $match->getStart(),
            'history' => $match->getHistory(),
        ];
    }
}
