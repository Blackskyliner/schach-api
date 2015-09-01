<?php

namespace Htwdd\Chessapi\DataTransformer;

use Htwdd\Chessapi\Entity\Match;
use Htwdd\Chessapi\UrlGeneratorAwareInterface;
use Htwdd\Chessapi\UrlGeneratorAwareTrait;

/**
 * Dieser Datentransformer beschreibt die öffentlicher Ressourcendarstellung
 * einer Partie, bzw. eines Matches
 */
class MatchTransformer
{
    /**
     * Diese Funktion wandelt ein Match Objekt in ein Array um.
     * Dabei werden nur die für die öffentliche Darstellung definierten Felder zurückgegeben.
     *
     * @param Match $match
     * @return array
     */
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
