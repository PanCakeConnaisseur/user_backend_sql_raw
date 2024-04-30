<?php

namespace OCA\UserBackendSqlRaw;

interface IHashPassword
{
    /**
     * Validation method that will receive the password string and hash and need to return a boolean value
     *
     * @return boolean true: is valid, false: invalid
     */
    public function validate(string $password, string $hash): bool;

    public function hashPassword(string $password): string;
}
