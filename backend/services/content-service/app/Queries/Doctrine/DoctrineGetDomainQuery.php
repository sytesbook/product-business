<?php

namespace App\Queries\Doctrine;

use App\Entities\Domain;
use App\Queries\GetDomainQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineGetDomainQuery implements GetDomainQuery
{
    /** @var EntityRepository<Domain> */
    private EntityRepository $repository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(Domain::class);
    }

    public function execute(string $siteUid, string $domainUid): ?array
    {
        $domain = $this->repository->find($domainUid);

        if ($domain === null || $domain->getSite()->getUid() !== $siteUid) {
            return null;
        }

        return [
            'archetype'   => 'document',
            'identifiers' => ['uid' => $domain->getUid()],
            'header'      => ['isPrimary' => $domain->isPrimary()],
            'body'        => ['domain' => $domain->getDomain()],
        ];
    }
}
