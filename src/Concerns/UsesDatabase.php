<?php

namespace Origami\Cart\Concerns;

use Illuminate\Database\DatabaseManager;

trait UsesDatabase
{
    /**
     * Instance of the session manager.
     *
     * @var DatabaseManager
     */
    protected $database;

    public function usesDatabase()
    {
        return isset($this->config['database_name']) && ! empty($this->config['database_name']);
    }

    public function saveToDatabase($identifier)
    {
        $content = $this->getContent();

        if ($this->storedCartWithIdentifierExists($identifier)) {
            throw new CartAlreadyStoredException("A cart with identifier {$identifier} was already stored.");
        }

        $this->getDatabaseConnection()
            ->table($this->getDatabaseTableName())
            ->insert([
                'identifier' => $identifier,
                'instance' => $this->currentInstance(),
                'content' => serialize($content)
            ]);

        $this->events->dispatch(new CartStored);
    }
    
    public function restoreFromDatabase($identifier)
    {
        if (!$this->storedCartWithIdentifierExists($identifier)) {
            return;
        }

        $stored = $this->getConnection()->table($this->getTableName())
            ->where('identifier', $identifier)->first();

        $storedContent = unserialize($stored->content);

        $currentInstance = $this->currentInstance();

        $content = $this->getContent();

        foreach ($storedContent as $cartItem) {
            $content->put($cartItem->rowId, $cartItem);
        }

        $this->events->dispatch(new CartRestored);

        $this->session->put($this->instance, $content);

        $this->getDatabaseConnection()
                ->table($this->getDatabaseTableName())
                ->where('identifier', $identifier)->delete();
    }

    /**
     * @param $identifier
     * @return bool
     */
    protected function storedCartWithIdentifierExists($identifier)
    {
        return $this->getDatabaseConnection()
                    ->table($this->getDatabaseTableName())
                    ->where('identifier', $identifier)
                    ->exists();
    }

    public function setDatabaseManager(DatabaseManager $database)
    {
        $this->database = $database;
        return $this;
    }

    /**
     * Get the database connection.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function getDatabaseConnection()
    {
        return $this->database->connection(array_get($this->config, 'database.connection'));
    }

    /**
     * Get the database table name.
     *
     * @return string
     */
    protected function getDatabaseTableName()
    {
        return array_get($this->config, 'database.table', 'cart');
    }
}
