<?php

namespace OCA\UserBackendSqlRaw;

class HashPassword implements IHashPassword
{
    public function validate(string $password, string $hash): bool
    {
        return md5($password) === $hash;
    }

    public function hashPassword(string $password): string
    {
        return md5($password);
    }
}
