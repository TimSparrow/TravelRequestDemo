<?php

class Credentials
{

    public function __construct(private readonly string $username,
                                private readonly string $password,
    private readonly int $companyId) {}

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
    public function getCompanyId(): int
    {
        return $this->companyId;
    }
}