<?php

namespace App\Repositories;

use App\Entities\Page;

interface PageRepositoryInterface
{
    public function find(string $uid): ?Page;

    public function save(Page $page): void;

    public function delete(Page $page): void;
}
