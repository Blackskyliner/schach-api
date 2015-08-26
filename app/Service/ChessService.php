<?php

namespace Htwdd\Chessapi\Service;

use Chess\Game\ChessGame;
use Htwdd\Chessapi\Exception\InvalidChessStateException;

class ChessService
{
    /** @var ChessGame  */
    private $chessEngine;

    /**
     * ChessService constructor.
     */
    public function __construct()
    {
        $this->chessEngine = new ChessGame();
    }

    /**
     * @param ChessGame $chessEngine
     */
    public function setChessEngine(ChessGame $chessEngine)
    {
        $this->chessEngine = $chessEngine;
    }

    private function initEngine($start, array $history)
    {
        $this->chessEngine->resetGame(trim($start));
        foreach ($history as $sanMove) {
            $sanMove = trim($sanMove);
            /** @var \PEAR_Error $error */
            if (($error = $this->chessEngine->moveSAN($sanMove)) !== true) {
                throw new InvalidChessStateException($error->getMessage());
            }
        }
    }

    /**
     * @param $start
     * @param array $history
     * @throws InvalidChessStateException if a move could not be made
     */
    public function validate($start, array $history)
    {
        $this->initEngine($start, $history);
    }

    /**
     * Rturns the current state of the game after applying all moves from the history
     * @param $start
     * @param array $history
     * @throws InvalidChessStateException if a move could not be made
     */
    public function getCurrentFen($start, array $history)
    {
        $this->initEngine($start, $history);
        return $this->chessEngine->renderFen();
    }
}
