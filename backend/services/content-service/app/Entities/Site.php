<?php

namespace App\Entities;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sites')]
class Site
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 64, nullable: false)]
    private string $uid;

    #[ORM\Column(type: 'string', length: 64, nullable: false)]
    private string $status;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private string $title;

    #[ORM\OneToMany(targetEntity: Domain::class, mappedBy: 'site')]
    private Collection $domains;

    #[ORM\OneToMany(targetEntity: Page::class, mappedBy: 'site')]
    private Collection $pages;

    public function __construct(string $uid, string $status, string $title)
    {
        $this->uid = $uid;
        $this->status = $status;
        $this->title = $title;
        $this->domains = new ArrayCollection();
        $this->pages = new ArrayCollection();
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDomains(): Collection
    {
        return $this->domains;
    }

    public function getPages(): Collection
    {
        return $this->pages;
    }
}
