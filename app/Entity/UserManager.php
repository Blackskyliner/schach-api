<?php

namespace Htwdd\Chessapi\Entity;

use Htwdd\Chessapi\Service\ManagerInterface;

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
     * @inheritDoc
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
     * @inheritDoc
     */
    protected function getEntityPath()
    {
        // Dadurch bilden wir die Dateistruktur auf die URL Struktur ab.
        return 'users';
    }
}
