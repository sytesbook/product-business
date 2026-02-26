<?php

namespace App\Queries\Doctrine;

use App\Queries\RoutingTablesQuery;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineRoutingTablesQuery implements RoutingTablesQuery
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function execute(array $domainNames = []): array
    {
        $dql = '
            SELECT d.domain    AS domainName,
                   d.isPrimary AS isPrimary,
                   s.uid       AS siteUid,
                   p.uid       AS pageUid,
                   p.path      AS pagePath,
                   pd.domain   AS primaryDomain
            FROM App\Entities\Domain d
            JOIN d.site s
            LEFT JOIN s.pages p
            LEFT JOIN s.domains pd WITH pd.isPrimary = true
        ';

        if (!empty($domainNames)) {
            $dql .= ' WHERE d.domain IN (:domains)';
        }

        $dql .= ' ORDER BY d.domain';

        $query = $this->entityManager->createQuery($dql);

        if (!empty($domainNames)) {
            $query->setParameter('domains', $domainNames);
        }

        $rows = $query->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $domain = $row['domainName'];

            if ((bool) $row['isPrimary']) {
                if (!isset($result[$domain])) {
                    $result[$domain] = ['site' => $row['siteUid'], 'pages' => []];
                }
                if ($row['pageUid'] !== null) {
                    $result[$domain]['pages'][$row['pagePath']] = $row['pageUid'];
                }
            } else {
                if (!isset($result[$domain])) {
                    $result[$domain] = ['redirect' => $row['primaryDomain']];
                }
            }
        }

        return $result;
    }
}
