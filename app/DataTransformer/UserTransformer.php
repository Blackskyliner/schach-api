<?php

namespace Htwdd\Chessapi\DataTransformer;

use Htwdd\Chessapi\Entity\User;

class UserTransformer
{
    public function toArray(User $user)
    {
        return [
            'id' => $user->getId(),
            'name' => $user->getName(),
        ];
    }
}
