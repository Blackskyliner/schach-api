<?php

namespace Htwdd\Chessapi\Service;

/**
 * Dieses Interface spiegelt eine Schach-Engine innerhalb der API dar.
 */
interface ChessEngineInterface {
    const COST_MILLISECONDS = 0;
    const COST_ITERATIONS = 1;
    const COST_DEPTH = 2;

    /**
     * @param string $fen Aktuelles Spielbrett in FEN Notation
     * @param int $costType einer der vom Interface definierten COST_* Typen.
     * @param int $costValue wertigkeit des jeweiligen Kostentyps
     *
     * @return string
     *
     * @throws \InvalidArgumentException kann geworfen werden, wenn ein Kostentyp nicht unterstützt wird.
     * @throws \UnexpectedValueException wird geworfen, wenn die FEN ungültig ist.
     */
    public function calculateBestMove($fen, $costType, $costValue);
}