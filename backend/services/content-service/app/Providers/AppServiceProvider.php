<?php

namespace App\Providers;

use App\Queries\DoctrineRoutingTablesQuery;
use App\Queries\RoutingTablesQuery;
use App\Repositories\DoctrineDomainRepository;
use App\Repositories\DoctrinePageRepository;
use App\Repositories\DoctrineSiteRepository;
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
