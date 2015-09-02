<?php

namespace Htwdd\Chessapi\Service;

class ChenardEngine implements ChessEngineInterface {

    protected $server;
    protected $port;


    /**
     * ChenardEngine constructor.
     */
    public function __construct($server, $port)
    {
        $this->server = $server;
        $this->port = $port;
    }


    /**
     * @inheritDoc
     */
    public function calculateBestMove($start, array $history, $costType, $costValue)
    {
        if ($costType != ChessEngineInterface::COST_MILLISECONDS) {
            throw new \UnexpectedValueException('The Chenard engine only supports milliseconds as time parameter.');
        }

        $sock = @fsockopen($this->server, $this->port);
        if ($sock === false) {
            return null;
        }

        fwrite($sock, 'new'."\n");
        $answer = fread($sock, 1000);
        if (strpos($answer, 'OK') === false) {
            return null;
        }
        fclose($sock);


        $sock = fsockopen($this->server, $this->port);
        fwrite($sock, 'move '.implode(' ', $history)."\n");
        $answer = fread($sock, 1000);
        if (strpos($answer, 'OK') === false) {
            return null;
        }
        fclose($sock);

        $sock = fsockopen($this->server, $this->port);
        if (strpos($answer, 'OK') !== false) {
            fwrite($sock, 'think '.$costValue."\n");

            $san = $pen = null;

            $answer = fread($sock, 1000);
            fclose($sock);
            if (strpos($answer, 'GAME_OVER') !== false) {
                return 'GAME_OVER';
            }
            sscanf($answer, 'OK %s %s', $pen, $san);
            if ($san) {
                return $san;
            }
        }

        return null;
    }
}
