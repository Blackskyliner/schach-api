<?php

namespace Htwdd\Chessapi\Service;

use Chess\Game\ChessGame;
use Htwdd\Chessapi\Exception\InvalidChessStateException;

/**
 * Dieser Service stellt Funktionen bereit, um eine Partie Schach zu verwalten.
 */
class ChessService
{
    /** @var ChessGame */
    private $chessEngine;

    /** @var  ChessEngineInterface */
    private $kiEngine;


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
     * @param ChessEngineInterface $kiEngine
     */
    public function setChessKi(ChessEngineInterface $kiEngine)
    {
        $this->kiEngine = $kiEngine;
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
     * Initialisiert die verwendete SchachEngine.
     *
     * @param $start
     * @param array $history
     * @throws InvalidChessStateException wenn die Historie ungültig ist.
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

    /**
     * Fragt eine SchachKI nach dem nächsten Zug.
     * Gibt numm zurück, wenn kein Zug gefunden wurde, bzw. die KI nicht konfiguriert/erreichbar ist.
     *
     * @param $start
     * @param array $history
     * @param $time
     *
     * @return string|null Bester Zug oder NULL
     */
    public function think($start, array $history, $time)
    {
        if ($this->kiEngine) {
            try {
                return $this->kiEngine->calculateBestMove(
                    $start,
                    $history,
                    ChessEngineInterface::COST_MILLISECONDS,
                    $time
                );
            } catch (\Exception $e) {
            }
        }

        return null;
    }
}
