<?php

namespace App\Repositories;

use App\Entities\Domain;
use App\Entities\Site;

interface DomainRepositoryInterface
{
    public function find(string $uid): ?Domain;

    public function findByDomain(string $domain): ?Domain;

    public function findPrimaryBySite(Site $site): ?Domain;

    public function save(Domain $domain): void;

    public function delete(Domain $domain): void;
}
