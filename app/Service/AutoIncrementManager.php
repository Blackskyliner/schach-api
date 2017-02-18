<?php

namespace Htwdd\Chessapi\Service;

use Htwdd\Chessapi\Exception\AutoIncrementException;

/**
 * Diese Klasse setzt einen AutoIncrement Mechanismus auf Dateisystemebene um.
 */
class AutoIncrementManager
{
    /** @var FileManager */
    private $fileManager;

    /**
     * MatchManager constructor.
     *
     * @param FileManager $fm
     */
    public function __construct(FileManager $fm)
    {
        $this->fileManager = $fm;
    }

    /**
     * Setzt das Autoincrement im übergebenen Objekt auf die angegebene ID Property.
     *
     * @param object $object welches des AutoIncrement gesetzt bekommen soll.
     * @param string $idProperty gibt den Namen der Property des $object an, wo die ID reingeschrieben werden soll.
     * @param int $autoIncrement entspricht der ID, kann über @see AutoIncrementManager::getNextIncrement
     *                           generiert werden.
     */
    public function setAutoIncrement($object, $idProperty, $autoIncrement)
    {
        // Da die ID eigentlich private ist müssen wir diese für die Operation
        // über die Reflection API beschreibbar machen.
        $reflect = new \ReflectionClass($object);
        $reflectProperty = $reflect->getProperty($idProperty);
        $reflectProperty->setAccessible(true);
        $reflectProperty->setValue($object, $autoIncrement);
        $reflectProperty->setAccessible(false);
    }

    /**
     * Holt die nächste ID für ein Objekt.
     *
     * @param $object
     * @return int|null
     * @throws AutoIncrementException
     */
    public function getNextIncrement($object)
    {
        $file = $this->getIncrementFile($object);
        if ($file->isReadable() && $file->isWritable() && $file->flock(LOCK_EX)) {
            // Holen des Increments
            $id = (int)$file->fgets() ?: 1;

            // Setzen des nächsten Increments
            $file->ftruncate(0);
            $file->rewind();
            $file->fwrite((string)($id + 1));
            $file->fflush();

            $file->flock(LOCK_UN);

            return $id;
        }

        throw new AutoIncrementException('Could not get Increment for: ' . get_class($object));
    }

    /**
     * Gibt die Increment-Datei für die Objekt Klasse zurück.
     *
     * @param object $object
     * @return \SplFileObject
     *
     * @throws \RuntimeException wenn die Datei nicht geöffnet werden konnte.
     */
    public function getIncrementFile($object)
    {
        $incrementName = '.auto_increment_' . strtolower(
                basename(
                    str_replace(
                        '\\',
                        '/',
                        get_class($object)
                    )
                )
            );

        return $this->getFileManager()->openFile($incrementName, 'c+', true);
    }

    /**
     * @return FileManager
     */
    protected function getFileManager()
    {
        return $this->fileManager;
    }
}
