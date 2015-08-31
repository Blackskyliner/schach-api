<?php

namespace Htwdd\Chessapi\Entity;

use Htwdd\Chessapi\Service\ManagerInterface;

class UserManager extends AbstractEntityManager
{
    /** @var  MatchManager */
    protected $matchManager;

    /**
     * @inheritDoc
     *
     * @param User $entity
     * TODO: durch neue methode im manager direkt im abstract ersetzen :D
     *
     */
    protected function getFileNameForObject($entity)
    {
        return $this->getFileNameForObjectId($entity->getId());
    }

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

            // TODO: if a player gets deleted set the other player as winner?

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
        return 'users';
    }
}
