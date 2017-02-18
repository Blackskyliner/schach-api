<?php

namespace Htwdd\Chessapi\Entity;

/**
 * Diese Klasse kümmert sich um die Verwaltung der User Objekte im Dateisystem.
 */
class UserManager extends AbstractEntityManager
{
    /** @var  MatchManager */
    protected $matchManager;

    /**
     * @param MatchManager $manger
     */
    public function setMatchManager(MatchManager $manger)
    {
        $this->matchManager = $manger;
    }

    /**
     * Löscht die übergebene Entität.
     *
     * @param object $entity Entität, die gelöscht werden soll
     * @return bool
     */
    public function delete($entity)
    {
        // Erease foreign keys.
        foreach ($this->matchManager->listAll() as $matchId) {
            /** @var Match $match */
            $match = $this->matchManager->load($matchId);
            $black = $this->loadByResource($match->getBlack());
            $white = $this->loadByResource($match->getWhite());
            $changed = false; // don't tamper with access time if not necessary.

            if ($white && $white->getId() === $entity->getId()) {
                $match->setWhite(null);
                $changed = true;
            }

            if ($black && $black->getId() === $entity->getId()) {
                $match->setBlack(null);
                $changed = true;
            }

            if ($changed) {
                $this->matchManager->save($match);
            }
        }

        // Delete the user
        parent::delete($entity);
    }

    /**
     * Gibt den Entity Pfad relativ zum verwendeten FileManager zurück.
     * @return string
     */
    protected function getEntityPath()
    {
        // Dadurch bilden wir die Dateistruktur auf die URL Struktur ab.
        return 'users';
    }
}
