<?php

namespace App\Queries\Doctrine;

use App\Entities\Page;
use App\Queries\GetPageQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineGetPageQuery implements GetPageQuery
{
    /** @var EntityRepository<Page> */
    private EntityRepository $repository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(Page::class);
    }

    public function execute(string $siteUid, string $pageUid): ?array
    {
        $page = $this->repository->find($pageUid);

        if ($page === null || $page->getSite()->getUid() !== $siteUid) {
            return null;
        }

        return [
            'archetype'   => 'document',
            'identifiers' => ['uid' => $page->getUid()],
            'body'        => ['title' => $page->getTitle(), 'path' => $page->getPath()],
        ];
    }
}
