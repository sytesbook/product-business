<?php

namespace App\Queries;

interface GetPageQuery
{
    /**
     * Returns null if the site or page does not exist, or the page does not belong to the site.
     */
    public function execute(string $siteUid, string $pageUid): ?array;
}
