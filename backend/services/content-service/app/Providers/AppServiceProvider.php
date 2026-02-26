<?php

namespace App\Providers;

use App\Queries\Doctrine\DoctrineGetDomainQuery;
use App\Queries\Doctrine\DoctrineGetPageQuery;
use App\Queries\Doctrine\DoctrineGetSiteQuery;
use App\Queries\Doctrine\DoctrineListDomainsQuery;
use App\Queries\Doctrine\DoctrineListPagesQuery;
use App\Queries\Doctrine\DoctrineListSitesQuery;
use App\Queries\Doctrine\DoctrineRoutingTablesQuery;
use App\Queries\GetDomainQuery;
use App\Queries\GetPageQuery;
use App\Queries\GetSiteQuery;
use App\Queries\ListDomainsQuery;
use App\Queries\ListPagesQuery;
use App\Queries\ListSitesQuery;
use App\Queries\RoutingTablesQuery;
use App\Repositories\Doctrine\DoctrineDomainRepository;
use App\Repositories\Doctrine\DoctrinePageRepository;
use App\Repositories\Doctrine\DoctrineSiteRepository;
use App\Repositories\DomainRepositoryInterface;
use App\Repositories\PageRepositoryInterface;
use App\Repositories\SiteRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SiteRepositoryInterface::class, DoctrineSiteRepository::class);
        $this->app->bind(DomainRepositoryInterface::class, DoctrineDomainRepository::class);
        $this->app->bind(PageRepositoryInterface::class, DoctrinePageRepository::class);
        $this->app->bind(RoutingTablesQuery::class, DoctrineRoutingTablesQuery::class);
        $this->app->bind(ListSitesQuery::class, DoctrineListSitesQuery::class);
        $this->app->bind(GetSiteQuery::class, DoctrineGetSiteQuery::class);
        $this->app->bind(ListDomainsQuery::class, DoctrineListDomainsQuery::class);
        $this->app->bind(GetDomainQuery::class, DoctrineGetDomainQuery::class);
        $this->app->bind(ListPagesQuery::class, DoctrineListPagesQuery::class);
        $this->app->bind(GetPageQuery::class, DoctrineGetPageQuery::class);
    }

    public function boot(): void
    {
        $em = $this->app->make(EntityManagerInterface::class);
        $em->getConnection()->getConfiguration()->setSchemaAssetsFilter(
            static function (mixed $asset): bool {
                $name = is_string($asset) ? $asset : $asset->getName();
                return $name !== 'migrations';
            }
        );
    }
}
