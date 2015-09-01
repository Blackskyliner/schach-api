<?php

namespace Htwdd\Chessapi\DataTransformer;

use Htwdd\Chessapi\Entity\User;

/**
 * Dieser Datentransformer beschreibt die öffentlicher Ressourcendarstellung
 * eines Users, bzw. eines Spielers
 */
class UserTransformer
{
    /**
     * Diese Funktion wandelt ein User Objekt in ein Array um.
     * Dabei werden nur die für die öffentliche Darstellung definierten Felder zurückgegeben.
     *
     * @param User $user
     * @return array
     */
    public function toArray(User $user)
    {
        return [
            'id' => $user->getId(),
            'name' => $user->getName(),
        ];
    }
}
