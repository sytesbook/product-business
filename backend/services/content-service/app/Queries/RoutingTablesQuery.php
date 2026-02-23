<?php

namespace App\Queries;

interface RoutingTablesQuery
{
    /**
     * @param string[] $domainNames  Empty array returns all domains.
     * @return array<string, array{site: string, pages: array<string, string>}|array{redirect: string}>
     */
    public function execute(array $domainNames = []): array;
}
