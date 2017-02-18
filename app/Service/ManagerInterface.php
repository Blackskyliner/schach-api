<?php

namespace Htwdd\Chessapi\Service;

/**
 * Interface für einen manager, der die im System vorhandenen Entitäten verwalten kann.
 */
interface ManagerInterface
{
    /**
     * Speichert die übergebene Entität.
     *
     * @param object $entity Entität, die gespeichert werden soll.
     * @return bool true, wenn erfolgreich gespeichert.
     */
    public function save($entity);

    /**
     * Löscht die übergebene Entität.
     *
     * @param object $entity Entität, die gelöscht werden soll
     * @return bool
     */
    public function delete($entity);

    /**
     * Lädt eine Entität anhand der übergebenen ID Merkmale.
     *
     * Siehe jeweils spezifische Managerdokumentation, wie der identifizierende Schlüssel definiert ist.
     *
     * @param mixed $id Der identfizierende Schlüssel für die Entität.
     * @return object|null
     */
    public function load($id);
}
