<?php

namespace Htwdd\Chessapi\Service;

/**
 * Diese Klasse dient der Verwaltung von Dateien im Dateisystem.
 * Dabei wird ein, beim erstellen des Objektes angegebenes, Verzeichnis verwaltet.
 */
class FileManager
{
    private $directory;

    /**
     * FileManager constructor.
     *
     * @param string $directory
     */
    public function __construct($directory)
    {
        $this->directory = $directory;
    }

    /**
     * Löscht die angegebene Datei in dem vom manager verwalteten Verzeichniss.
     *
     * @param string $filename gibt an, welche Datei gelöscht werden soll.
     */
    public function removeFile($filename)
    {
        $fullDirectoryPath = rtrim($this->getDirectory() . DIRECTORY_SEPARATOR . dirname($filename), '/.');
        $filePath = $fullDirectoryPath . DIRECTORY_SEPARATOR . basename($filename);

        if (file_exists($filePath) && is_writable($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Gibt das vom Manager verwaltete Verzeichniss zurück.
     *
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * Schreibt einen Payload, samt optionalem file locking in die angegebene Datei.
     *
     * @param string $filename Datei, in welche geschrieben werden soll.
     * @param string $payload Payload, welcher in die Datei geschrieben werden soll.
     * @param int $lockMode @see fopen() für gülige Modi, bei 0 wird kein file locking durchgeführt.
     *                      Standardmäßig wird die Datei exklusiv gelockt, bis der Inhalt geschrieben wurde.
     * @return bool gibt true zurück, wenn die Datei geschrieben wurd, sonst false.
     */
    public function writeFile($filename, $payload, $lockMode = LOCK_EX)
    {
        try {
            $file = $this->openFile($filename, 'c', true);
            if ($file->isWritable() && ($lockMode === 0 || $file->flock($lockMode))) {
                $file->ftruncate(0);
                $file->rewind();

                $writtenPayload = $file->fwrite($payload);

                if ($lockMode !== 0) {
                    $file->flock(LOCK_UN);
                }

                if ($writtenPayload !== null) {
                    return true;
                }
            }
        } catch (\RuntimeException $e) {
        }

        return false;
    }

    /**
     * Öffnet die angegebene Datei in dem vom Manager verwalteten Verzeichniss.
     *
     * @param string $filename gibt an welche Datei geöffnet werden soll.
     * @param string $mode gibt an wie die Datei geöffnet werden soll.
     * @param bool $createDirs gibt an ob der Pfad zur Datei erstellt werden soll wenn dieser nicht existiert.
     *
     * @see http://php.net/manual/en/function.fopen.php für gültige $mode.
     *
     * @return \SplFileObject
     * @throws \RuntimeException wenn die Datei nicht geöffnet werden konnte.
     */
    public function openFile($filename, $mode = 'r', $createDirs = false)
    {
        if ($filename === null || $filename === '') {
            throw new \RuntimeException('Please specify an valid filename.');
        }

        $fullDirectoryPath = rtrim($this->getDirectory() . DIRECTORY_SEPARATOR . dirname($filename), '/.');

        if ($createDirs === true && !file_exists($fullDirectoryPath)) {
            if(!mkdir($fullDirectoryPath, 0750, true) && !is_dir($fullDirectoryPath)){
                throw new \RuntimeException('Could not access filepath');
            }
        }

        return new \SplFileObject($fullDirectoryPath . DIRECTORY_SEPARATOR . basename($filename), $mode);
    }

    /**
     * Gibt die Angegebene Datei zurück und nutzt optional file locking zum öffnen der Datei.
     *
     * @param string $filename Datei, in welche geschrieben werden soll.
     * @param int $lockMode @see fopen() für gülige Modi, bei 0 wird kein file locking durchgeführt.
     *                      Standardmäßig wird die Datei shared gelockt, bis der Inhalt geschrieben wurde.
     * @return string|null Kompletter Inhalt der Datei, im Fehlerfall wird NULL zurückgegeben.
     */
    public function readFile($filename, $lockMode = LOCK_SH)
    {
        try {
            $file = $this->openFile($filename, 'r', true);
            if ($file->isReadable() && ($lockMode === 0 || $file->flock($lockMode))) {
                $payload = '';

                while (!$file->eof()) {
                    $payload .= $file->fgets();
                }

                $file->flock(LOCK_UN);

                return $payload;
            }
        } catch (\RuntimeException $e) {
        }

        return null;
    }
}
