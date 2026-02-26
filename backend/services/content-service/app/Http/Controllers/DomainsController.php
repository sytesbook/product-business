<?php

namespace App\Http\Controllers;

use App\Entities\Domain;
use App\Queries\GetDomainQuery;
use App\Queries\ListDomainsQuery;
use App\Repositories\DomainRepositoryInterface;
use App\Repositories\SiteRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class DomainsController
{
    public function __construct(
        private readonly ListDomainsQuery $listDomainsQuery,
        private readonly GetDomainQuery $getDomainQuery,
        private readonly SiteRepositoryInterface $siteRepository,
        private readonly DomainRepositoryInterface $domainRepository,
    ) {}

    public function index(string $siteUid): JsonResponse
    {
        $result = $this->listDomainsQuery->execute($siteUid);

        if ($result === null) {
            return response()->json(['message' => 'Site not found.'], 404);
        }

        return response()->json($result);
    }

    public function show(string $siteUid, string $domainUid): JsonResponse
    {
        $domain = $this->getDomainQuery->execute($siteUid, $domainUid);

        if ($domain === null) {
            return response()->json(['message' => 'Domain not found.'], 404);
        }

        return response()->json($domain);
    }

    public function store(Request $request, string $siteUid): JsonResponse
    {
        $site = $this->siteRepository->find($siteUid);

        if ($site === null) {
            return response()->json(['message' => 'Site not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'data.archetype'   => ['required', 'string', 'in:document'],
            'data.body.domain' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $data = $validator->validated();
        $domainName = $data['data']['body']['domain'];

        if ($this->domainRepository->findByDomain($domainName) !== null) {
            return response()->json(['message' => 'Domain name is already taken.'], 400);
        }

        $domain = new Domain(
            bin2hex(random_bytes(8)),
            $domainName,
            false,
            $site,
        );

        $this->domainRepository->save($domain);

        return response()->json([
            'archetype'   => 'document',
            'identifiers' => ['uid' => $domain->getUid()],
            'header'      => ['isPrimary' => $domain->isPrimary()],
            'body'        => ['domain' => $domain->getDomain()],
        ], 201);
    }

    public function update(Request $request, string $siteUid, string $domainUid): JsonResponse
    {
        $site = $this->siteRepository->find($siteUid);

        if ($site === null) {
            return response()->json(['message' => 'Site not found.'], 404);
        }

        $domain = $this->domainRepository->find($domainUid);

        if ($domain === null || $domain->getSite()->getUid() !== $siteUid) {
            return response()->json(['message' => 'Domain not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'data.archetype'   => ['required', 'string', 'in:document'],
            'data.body.domain' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $data = $validator->validated();
        $domainName = $data['data']['body']['domain'];

        $existing = $this->domainRepository->findByDomain($domainName);
        if ($existing !== null && $existing->getUid() !== $domainUid) {
            return response()->json(['message' => 'Domain name is already taken.'], 400);
        }

        $domain->setDomain($domainName);
        $this->domainRepository->save($domain);

        return response()->json([
            'archetype'   => 'document',
            'identifiers' => ['uid' => $domain->getUid()],
            'header'      => ['isPrimary' => $domain->isPrimary()],
            'body'        => ['domain' => $domain->getDomain()],
        ]);
    }

    public function setAsPrimary(Request $request, string $siteUid, string $domainUid): JsonResponse
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

        $domain = $this->domainRepository->find($domainUid);

        if ($domain === null || $domain->getSite()->getUid() !== $siteUid) {
            return response()->json(['message' => 'Domain not found.'], 404);
        }

        $currentPrimary = $this->domainRepository->findPrimaryBySite($site);

        if ($currentPrimary !== null && $currentPrimary->getUid() !== $domainUid) {
            $currentPrimary->setIsPrimary(false);
            $this->domainRepository->save($currentPrimary);
        }

        $domain->setIsPrimary(true);
        $this->domainRepository->save($domain);

        return response()->json([
            'archetype'   => 'document',
            'identifiers' => ['uid' => $domain->getUid()],
            'header'      => ['isPrimary' => $domain->isPrimary()],
            'body'        => ['domain' => $domain->getDomain()],
        ]);
    }

    public function destroy(string $siteUid, string $domainUid): Response
    {
        $site = $this->siteRepository->find($siteUid);

        if ($site === null) {
            return response('Site not found.', 404);
        }

        $domain = $this->domainRepository->find($domainUid);

        if ($domain === null || $domain->getSite()->getUid() !== $siteUid) {
            return response('Domain not found.', 404);
        }

        $this->domainRepository->delete($domain);

        return response('', 204);
    }
}
