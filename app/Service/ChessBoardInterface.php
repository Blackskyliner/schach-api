<?php

namespace Htwdd\Chessapi\Service;

/**
 * Dieses Interface definiert eine mögliche Schnittstelle, welche durch eine ChessEngine
 * implementiert werden könnte.
 *
 * Dabei werden die minimalsten Funktionen vorrausgesetzt, um ein Schachspiel zu ermöglichen.
 * @todo implementieren einer Klasse, die über dieses Interface ein Schachspiel ermöglicht.
 */
interface ChessBoardInterface
{
    /**
     * @param string $fen Spielbrett Zustand in der FEN. Standardwert ist die Startposition eines Spiels.
     * @return bool
     * @throws \InvalidArgumentException wenn das übergebene Spielbrett oder die Notation ungültig sind.
     * @see https://en.wikipedia.org/wiki/Forsyth%E2%80%93Edwards_Notation
     */
    public function initGame($fen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1');

    /**
     * Bewegt eine Figur von $from nach $to. Dabei wird darauf geachtet, dass keine Schachregeln verletzt werden.
     *
     * @param string $from Bezeichnendes Feld (z.B. E1) von dem gerückt werden soll.
     * @param string $to Bezeichnendes Feld (z.B. B2) zu dem gerückt werden soll.
     * @return bool
     * @throws \InvalidArgumentException wenn der Zug nicht möglich ist.
     */
    public function moveSquare($from, $to);

    /**
     * Bewegt eine Figur anhand der Standard Algebraic Notation (SAN).
     * Dabei wird darauf geachtet, dass keine Schachregeln verletzt werden.
     *
     * @param string $san Eine dem gewünschten Zug entsprechende Notation.
     * @return bool
     * @throws \InvalidArgumentException wenn der Zug nicht möglich ist.
     * @see https://en.wikipedia.org/wiki/Algebraic_notation_%28chess%29
     */
    public function moveSan($san);

    /**
     * Gibt das aktuelle Spielfeld in der Forsyth-Edwards Notation zurück.
     * @return string
     */
    public function toFen();
}
