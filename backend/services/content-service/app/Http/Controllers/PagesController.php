<?php

namespace App\Http\Controllers;

use App\Entities\Page;
use App\Queries\GetPageQuery;
use App\Queries\ListPagesQuery;
use App\Repositories\PageRepositoryInterface;
use App\Repositories\SiteRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class PagesController
{
    public function __construct(
        private readonly ListPagesQuery $listPagesQuery,
        private readonly GetPageQuery $getPageQuery,
        private readonly SiteRepositoryInterface $siteRepository,
        private readonly PageRepositoryInterface $pageRepository,
    ) {}

    public function index(string $siteUid): JsonResponse
    {
        $result = $this->listPagesQuery->execute($siteUid);

        if ($result === null) {
            return response()->json(['message' => 'Site not found.'], 404);
        }

        return response()->json($result);
    }

    public function show(string $siteUid, string $pageUid): JsonResponse
    {
        $page = $this->getPageQuery->execute($siteUid, $pageUid);

        if ($page === null) {
            return response()->json(['message' => 'Page not found.'], 404);
        }

        return response()->json($page);
    }

    public function store(Request $request, string $siteUid): JsonResponse
    {
        $site = $this->siteRepository->find($siteUid);

        if ($site === null) {
            return response()->json(['message' => 'Site not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'data.archetype'  => ['required', 'string', 'in:document'],
            'data.body.title' => ['required', 'string', 'max:255'],
            'data.body.path'  => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $data = $validator->validated();

        $title = $data['data']['body']['title'];
        $path  = $data['data']['body']['path'];

        if ($this->pageRepository->findByPathAndSite($path, $site) !== null) {
            return response()->json(['message' => 'Path is already taken for this site.'], 400);
        }

        $page = new Page(
            bin2hex(random_bytes(8)),
            $title,
            $path,
            $site,
        );

        $this->pageRepository->save($page);

        return response()->json([
            'archetype'   => 'document',
            'identifiers' => ['uid' => $page->getUid()],
            'body'        => ['title' => $page->getTitle(), 'path' => $page->getPath()],
        ], 201);
    }

    public function update(Request $request, string $siteUid, string $pageUid): JsonResponse
    {
        $site = $this->siteRepository->find($siteUid);

        if ($site === null) {
            return response()->json(['message' => 'Site not found.'], 404);
        }

        $page = $this->pageRepository->find($pageUid);

        if ($page === null || $page->getSite()->getUid() !== $siteUid) {
            return response()->json(['message' => 'Page not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'data.archetype'  => ['required', 'string', 'in:document'],
            'data.body.title' => ['required', 'string', 'max:255'],
            'data.body.path'  => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $data = $validator->validated();

        $title = $data['data']['body']['title'];
        $path  = $data['data']['body']['path'];

        $existing = $this->pageRepository->findByPathAndSite($path, $site);
        if ($existing !== null && $existing->getUid() !== $pageUid) {
            return response()->json(['message' => 'Path is already taken for this site.'], 400);
        }

        $page->setTitle($title);
        $page->setPath($path);
        $this->pageRepository->save($page);

        return response()->json([
            'archetype'   => 'document',
            'identifiers' => ['uid' => $page->getUid()],
            'body'        => ['title' => $page->getTitle(), 'path' => $page->getPath()],
        ]);
    }

    public function destroy(string $siteUid, string $pageUid): Response
    {
        $site = $this->siteRepository->find($siteUid);

        if ($site === null) {
            return response('Site not found.', 404);
        }

        $page = $this->pageRepository->find($pageUid);

        if ($page === null || $page->getSite()->getUid() !== $siteUid) {
            return response('Page not found.', 404);
        }

        $this->pageRepository->delete($page);

        return response('', 204);
    }
}
