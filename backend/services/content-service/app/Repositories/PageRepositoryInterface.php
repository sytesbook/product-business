<?php

namespace App\Repositories;

use App\Entities\Page;
use App\Entities\Site;

interface PageRepositoryInterface
{
    public function find(string $uid): ?Page;

    public function findByPathAndSite(string $path, Site $site): ?Page;

    public function save(Page $page): void;

    public function delete(Page $page): void;
}
