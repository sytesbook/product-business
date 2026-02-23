<?php

namespace App\Repositories;

use App\Entities\Site;

interface SiteRepositoryInterface
{
    public function find(string $uid): ?Site;

    public function save(Site $site): void;

    public function delete(Site $site): void;
}
