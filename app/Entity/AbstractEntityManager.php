<?php

namespace Htwdd\Chessapi\Entity;

use Htwdd\Chessapi\Exception\AutoIncrementException;
use Htwdd\Chessapi\Exception\NotFoundException;
use Htwdd\Chessapi\Service\AutoIncrementManager;
use Htwdd\Chessapi\Service\FileManager;
use Htwdd\Chessapi\Service\ManagerInterface;

abstract class AbstractEntityManager implements ManagerInterface
{
    /** @var FileManager  */
    private $fileManager;

    /** @var  AutoIncrementManager */
    private $incrementManager;

    /**
     * MatchManager constructor.
     *
     * @param FileManager $fm
     */
    public function __construct(FileManager $fm, AutoIncrementManager $incrementManager)
    {
        $this->incrementManager = $incrementManager;
        $this->fileManager = $fm;
    }

    /**
     * @return AutoIncrementManager
     */
    protected function getAutoIncrementManager()
    {
        return $this->incrementManager;
    }

    /**
     * @return FileManager
     */
    protected function getFileManager()
    {
        return $this->fileManager;
    }

    /**
     * Gibt den Dateinamen für die übergebene Entity zurück.
     *
     * @param object $entity
     * @return string
     */
    abstract protected function getFileNameForObject($entity);

    /**
     * Gibt den Dateinamen für die übergebene EntityID zurück.
     *
     * @param integer $entityId
     * @return string
     */
    protected function getFileNameForObjectId($entityId)
    {
        return $this->getEntityPath().DIRECTORY_SEPARATOR.$entityId;
    }

    /**
     * Gibt den Entity Pfad relativ zum verwendeten FileManager zurück.
     * @return string
     */
    protected function getEntityPath()
    {
        return strtolower(
            basename(
                str_replace(
                    array('\\', 'Manager'),
                    array('/', ''),
                    get_called_class()
                )
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function save($entity)
    {
        // Create ID
        try {
            if ($this->getIdentifier($entity) === null) {
                $incrementId = $this->getAutoIncrementManager()->getNextIncrement($entity);
                $this->setIdentifier($entity, $incrementId);
            }
        } catch (AutoIncrementException $e) {
            return false;
        }

        $payload = serialize($entity);

        return $this->getFileManager()->writeFile($this->getFileNameForObject($entity), $payload);
    }

    /**
     * Sets the identifier on an managed entity.
     *
     * May be easily overwritten by extending manager.
     * For example if the managed entity does not have an 'id property.
     *
     * @param object $entity
     * @param integer $id
     */
    public function setIdentifier($entity, $id)
    {
        $this->getAutoIncrementManager()->setAutoIncrement($entity, 'id', $id);
    }

    /**
     * Gets the identifier on an managed entity.
     *
     * May be easily overwritten by extending manager.
     * For example if the managed entity does not have an 'id property.
     *
     * @param object $entity
     */
    public function getIdentifier($entity)
    {
        return $entity->getId();
    }

    /**
     * @inheritDoc
     */
    public function delete($entity)
    {
        $this->getFileManager()->removeFile($this->getFileNameForObject($entity));
    }

    /**
     * @inheritDoc
     */
    public function load($id)
    {
        $content = $this->getFileManager()->readFile($this->getFileNameForObjectId($id));
        if ($content !== null) {
            return unserialize($content);
        }

        return null;
    }

    public function loadByResource($path)
    {
        $content = $this->getFileManager()->readFile($path);
        if ($content !== null) {
            return unserialize($content);
        }

        return null;
    }

    /**
     * Gibt die ID alle existierenden Entitäten zurück.
     *
     * @return array
     */
    public function listAll()
    {
        $retVal = array();

        try {
            $directory = new \DirectoryIterator(
                $this->getFileManager()->getDirectory().DIRECTORY_SEPARATOR.$this->getEntityPath()
            );
            foreach ($directory as $directoryInformation) {
                if (!$directoryInformation->isDot() && $directoryInformation->isReadable()) {
                    $retVal[] = $directoryInformation->getFilename();
                }
            }
        } catch (\UnexpectedValueException $e) {
        }

        return $retVal;
    }
}
