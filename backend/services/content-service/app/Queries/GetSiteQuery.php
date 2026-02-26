<?php

namespace App\Queries;

interface GetSiteQuery
{
    public function execute(string $uid): ?array;
}
