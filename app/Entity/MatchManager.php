<?php

namespace Htwdd\Chessapi\Entity;

/**
 * Diese Klasse kümmert sich um die Verwaltung der Match Objekte im Dateisystem.
 */
class MatchManager extends AbstractEntityManager
{
    /**
     * @inheritDoc
     */
    protected function getEntityPath()
    {
        // Dadurch bilden wir die Dateistruktur auf die URL Struktur ab.
        return 'matches';
    }


}
