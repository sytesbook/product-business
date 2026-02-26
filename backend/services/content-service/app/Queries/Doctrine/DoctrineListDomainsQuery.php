<?php

namespace App\Queries\Doctrine;

use App\Entities\Domain;
use App\Entities\Site;
use App\Queries\ListDomainsQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineListDomainsQuery implements ListDomainsQuery
{
    /** @var EntityRepository<Site> */
    private EntityRepository $siteRepository;

    /** @var EntityRepository<Domain> */
    private EntityRepository $domainRepository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->siteRepository   = $entityManager->getRepository(Site::class);
        $this->domainRepository = $entityManager->getRepository(Domain::class);
    }

    public function execute(string $siteUid): ?array
    {
        $site = $this->siteRepository->find($siteUid);

        if ($site === null) {
            return null;
        }

        $domains = $this->domainRepository->findBy(['site' => $site]);

        return [
            'archetype' => 'document-collection',
            'items'     => array_map(static fn(Domain $domain) => [
                'archetype'   => 'document',
                'identifiers' => ['uid' => $domain->getUid()],
                'header'      => ['isPrimary' => $domain->isPrimary()],
                'body'        => ['domain' => $domain->getDomain()],
            ], $domains),
        ];
    }
}
