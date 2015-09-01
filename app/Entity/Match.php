<?php

namespace Htwdd\Chessapi\Entity;

/**
 * Dieses Objekt repräsentiert eine Partie.
 */
class Match
{
    /**
     * @var integer
     */
    protected $id;

    /**
     * @var string URI zu einem User
     */
    protected $white;

    /**
     * @var string URI zu einem User
     */
    protected $black;

    /**
     * @var string Startsituation in FEN
     */
    protected $start;

    /**
     * @var string[] Liste mit SAN Einträgen
     */
    protected $history;

    /**
     * Builds the initial state of the match object.
     */
    public function __construct()
    {
        $this->start = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
        $this->history = [];
    }

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getWhite()
    {
        return $this->white;
    }

    /**
     * @param string $white
     */
    public function setWhite($white)
    {
        $this->white = $white;
    }

    /**
     * @return string
     */
    public function getBlack()
    {
        return $this->black;
    }

    /**
     * @param $black
     */
    public function setBlack($black)
    {
        $this->black = $black;
    }

    /**
     * @return string
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * @param string $startFAN
     */
    public function setStart($startFAN)
    {
        $this->start = $startFAN;
    }

    /**
     * @return string[]
     */
    public function getHistory()
    {
        return $this->history;
    }

    /**
     * @param string[] $history
     */
    public function setHistory(array $history)
    {
        $this->history = $history;
    }

    /**
     * @param string $historyLAN
     */
    public function addHistory($historyLAN)
    {
        $this->history[] = $historyLAN;
    }
}
