<?php

namespace Htwdd\Chessapi\Entity;

use Htwdd\Chessapi\Exception\AutoIncrementException;
use Htwdd\Chessapi\Exception\NotFoundException;

class MatchManager extends AbstractEntityManager
{
    /**
     * @inheritDoc
     *
     * @param Match $entity
     * TODO: durch neue methode im manager direkt im abstract ersetzen :D
     */
    protected function getFileNameForObject($entity)
    {
        return $this->getFileNameForObjectId($entity->getId());
    }

    /**
     * @inheritDoc
     */
    protected function getEntityPath()
    {
        return 'matches';
    }


}
