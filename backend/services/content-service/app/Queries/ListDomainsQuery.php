<?php

namespace App\Queries;

interface ListDomainsQuery
{
    /**
     * Returns null if the site does not exist.
     */
    public function execute(string $siteUid): ?array;
}
