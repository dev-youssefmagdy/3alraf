<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown by down() of migrations whose up() rewrites data in a way that cannot be undone (restore a backup instead). */
class IrreversibleMigrationException extends RuntimeException
{
    public static function for(string $migration, string $why): self
    {
        return new self("Migration {$migration} is irreversible: {$why}. Restore a database backup instead of rolling back.");
    }
}
