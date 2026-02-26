<?php

namespace App\Queries\Doctrine;

use App\Entities\Page;
use App\Entities\Site;
use App\Queries\ListPagesQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineListPagesQuery implements ListPagesQuery
{
    /** @var EntityRepository<Site> */
    private EntityRepository $siteRepository;

    /** @var EntityRepository<Page> */
    private EntityRepository $pageRepository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->siteRepository = $entityManager->getRepository(Site::class);
        $this->pageRepository = $entityManager->getRepository(Page::class);
    }

    public function execute(string $siteUid): ?array
    {
        $site = $this->siteRepository->find($siteUid);

        if ($site === null) {
            return null;
        }

        $pages = $this->pageRepository->findBy(['site' => $site]);

        return [
            'archetype' => 'document-collection',
            'items'     => array_map(static fn(Page $page) => [
                'archetype'   => 'document',
                'identifiers' => ['uid' => $page->getUid()],
                'body'        => ['title' => $page->getTitle(), 'path' => $page->getPath()],
            ], $pages),
        ];
    }
}
