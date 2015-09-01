<?php

namespace Htwdd\Chessapi\Service;

use Chess\Game\ChessGame;
use Htwdd\Chessapi\Exception\InvalidChessStateException;

/**
 * Dieser Service stellt Funktionen bereit, um eine Partie Schach zu verwalten.
 */
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

    /**
     * Initialisiert die verwendete SchachEngine.
     *
     * @param $start
     * @param array $history
     */
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
     * Validiert eine übergebene Schachsituation.
     *
     * @param string $start Startsituation in der FEN
     * @param string[] $history Historie der Züge in SAN
     * @throws InvalidChessStateException wenn die FEN ungültig ist oder einer der Züge.
     */
    public function validate($start, array $history)
    {
        $this->initEngine($start, $history);
    }

    /**
     * Validiert und gibt die aktuelle Schachsituation nach dem Anwenden aller Züge zurück.
     *
     * @param string $start Startsituation in der FEN
     * @param string[] $history Historie der Züge in SAN
     * @throws InvalidChessStateException wenn die FEN ungültig ist oder einer der Züge.
     * @return string
     */
    public function getCurrentFen($start, array $history)
    {
        $this->initEngine($start, $history);
        return $this->chessEngine->renderFen();
    }
}
