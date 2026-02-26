<?php

namespace App\Queries;

interface GetDomainQuery
{
    /**
     * Returns null if the site or domain does not exist, or the domain does not belong to the site.
     */
    public function execute(string $siteUid, string $domainUid): ?array;
}
