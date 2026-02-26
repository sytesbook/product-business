<?php

namespace App\Http\Controllers;

use App\Entities\Site;
use App\Queries\GetSiteQuery;
use App\Queries\ListSitesQuery;
use App\Repositories\SiteRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class SitesController
{
    public function __construct(
        private readonly ListSitesQuery $listSitesQuery,
        private readonly GetSiteQuery $getSiteQuery,
        private readonly SiteRepositoryInterface $siteRepository,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->listSitesQuery->execute());
    }

    public function show(string $siteUid): JsonResponse
    {
        $site = $this->getSiteQuery->execute($siteUid);

        if ($site === null) {
            return response()->json(['message' => 'Site not found.'], 404);
        }

        return response()->json($site);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data.archetype'  => ['required', 'string', 'in:document'],
            'data.body.title' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $data = $validator->validated();

        $site = new Site(
            bin2hex(random_bytes(8)),
            'inactive',
            $data['data']['body']['title'],
        );

        $this->siteRepository->save($site);

        return response()->json([
            'archetype'   => 'document',
            'identifiers' => ['uid' => $site->getUid()],
            'header'      => ['status' => $site->getStatus()],
            'body'        => ['title' => $site->getTitle()],
        ], 201);
    }

    public function update(Request $request, string $siteUid): JsonResponse
    {
        $site = $this->siteRepository->find($siteUid);

        if ($site === null) {
            return response()->json(['message' => 'Site not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'data.archetype'  => ['required', 'string', 'in:document'],
            'data.body.title' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $data = $validator->validated();

        $site->setTitle($data['data']['body']['title']);
        $this->siteRepository->save($site);

        return response()->json([
            'archetype'   => 'document',
            'identifiers' => ['uid' => $site->getUid()],
            'header'      => ['status' => $site->getStatus()],
            'body'        => ['title' => $site->getTitle()],
        ]);
    }

    public function activate(Request $request, string $siteUid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data' => ['present', 'array', 'max:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $site = $this->siteRepository->find($siteUid);

        if ($site === null) {
            return response()->json(['message' => 'Site not found.'], 404);
        }

        $site->setStatus('active');
        $this->siteRepository->save($site);

        return response()->json([
            'archetype'   => 'document',
            'identifiers' => ['uid' => $site->getUid()],
            'header'      => ['status' => $site->getStatus()],
            'body'        => ['title' => $site->getTitle()],
        ]);
    }

    public function deactivate(Request $request, string $siteUid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data' => ['present', 'array', 'max:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $site = $this->siteRepository->find($siteUid);

        if ($site === null) {
            return response()->json(['message' => 'Site not found.'], 404);
        }

        $site->setStatus('inactive');
        $this->siteRepository->save($site);

        return response()->json([
            'archetype'   => 'document',
            'identifiers' => ['uid' => $site->getUid()],
            'header'      => ['status' => $site->getStatus()],
            'body'        => ['title' => $site->getTitle()],
        ]);
    }

    public function destroy(string $siteUid): Response
    {
        $site = $this->siteRepository->find($siteUid);

        if ($site === null) {
            return response('Site not found.', 404);
        }

        if (!$site->getDomains()->isEmpty() || !$site->getPages()->isEmpty()) {
            return response('Site has attached domains or pages and cannot be deleted.', 409);
        }

        $this->siteRepository->delete($site);

        return response('', 204);
    }
}
