<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'domains')]
#[ORM\UniqueConstraint(name: 'domains_domain_unique', columns: ['domain'])]
class Domain
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 64, nullable: false)]
    private string $uid;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private string $domain;

    #[ORM\Column(name: 'is_primary', type: 'boolean', nullable: false)]
    private bool $isPrimary;

    #[ORM\ManyToOne(targetEntity: Site::class, inversedBy: 'domains')]
    #[ORM\JoinColumn(name: 'site_uid', referencedColumnName: 'uid', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    public function __construct(string $uid, string $domain, bool $isPrimary, Site $site)
    {
        $this->uid = $uid;
        $this->domain = $domain;
        $this->isPrimary = $isPrimary;
        $this->site = $site;
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): void
    {
        $this->isPrimary = $isPrimary;
    }

    public function getSite(): Site
    {
        return $this->site;
    }
}
