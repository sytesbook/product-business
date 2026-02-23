<?php

namespace App\Http\Controllers;

use App\Queries\RoutingTablesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoutingTablesController
{
    public function __construct(
        private readonly RoutingTablesQuery $routingTablesQuery,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $domainNames = $request->query('domains', []);

        $result = $this->routingTablesQuery->execute(
            is_array($domainNames) ? $domainNames : []
        );

        return response()->json($result);
    }
}
