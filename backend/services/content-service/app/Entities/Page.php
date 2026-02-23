<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pages')]
#[ORM\UniqueConstraint(name: 'pages_site_uid_path_unique', columns: ['site_uid', 'path'])]
class Page
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 64, nullable: false)]
    private string $uid;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private string $title;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private string $path;

    #[ORM\ManyToOne(targetEntity: Site::class, inversedBy: 'pages')]
    #[ORM\JoinColumn(name: 'site_uid', referencedColumnName: 'uid', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    public function __construct(string $uid, string $title, string $path, Site $site)
    {
        $this->uid = $uid;
        $this->title = $title;
        $this->path = $path;
        $this->site = $site;
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getSite(): Site
    {
        return $this->site;
    }
}
