<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sites')->insert([
            ['uid' => 'customer-1', 'status' => 'active', 'title' => 'Customer 1'],
            ['uid' => 'customer-2', 'status' => 'active', 'title' => 'Customer 2'],
            ['uid' => 'customer-3', 'status' => 'active', 'title' => 'Customer 3'],
        ]);

        DB::table('domains')->insert([
            ['uid' => 'customer-1-primary', 'domain' => 'customer-1.test', 'is_primary' => true,  'site_uid' => 'customer-1'],
            ['uid' => 'customer-1-secondary',     'domain' => 'secondary.customer-1.test', 'is_primary' => false, 'site_uid' => 'customer-1'],
            ['uid' => 'customer-2-primary', 'domain' => 'customer-2.test', 'is_primary' => true,  'site_uid' => 'customer-2'],
            ['uid' => 'customer-2-secondary',     'domain' => 'secondary.customer-2.test', 'is_primary' => false, 'site_uid' => 'customer-2'],
            ['uid' => 'customer-3-primary', 'domain' => 'customer-3.test', 'is_primary' => true,  'site_uid' => 'customer-3'],
            ['uid' => 'customer-3-secondary',     'domain' => 'secondary.customer-3.test', 'is_primary' => false, 'site_uid' => 'customer-3'],
        ]);

        DB::table('pages')->insert([
            ['uid' => 'customer-1-page-1', 'title' => 'Page 1', 'path' => '/page-1', 'site_uid' => 'customer-1'],
            ['uid' => 'customer-1-page-2', 'title' => 'Page 2', 'path' => '/page-2', 'site_uid' => 'customer-1'],
            ['uid' => 'customer-1-page-3', 'title' => 'Page 3', 'path' => '/page-3', 'site_uid' => 'customer-1'],
            ['uid' => 'customer-2-page-1', 'title' => 'Page 1', 'path' => '/page-1', 'site_uid' => 'customer-2'],
            ['uid' => 'customer-2-page-2', 'title' => 'Page 2', 'path' => '/page-2', 'site_uid' => 'customer-2'],
            ['uid' => 'customer-2-page-3', 'title' => 'Page 3', 'path' => '/page-3', 'site_uid' => 'customer-2'],
            ['uid' => 'customer-3-page-1', 'title' => 'Page 1', 'path' => '/page-1', 'site_uid' => 'customer-3'],
            ['uid' => 'customer-3-page-2', 'title' => 'Page 2', 'path' => '/page-2', 'site_uid' => 'customer-3'],
            ['uid' => 'customer-3-page-3', 'title' => 'Page 3', 'path' => '/page-3', 'site_uid' => 'customer-3'],
        ]);
    }

    public function down(): void
    {
        DB::table('sites')->whereIn('uid', ['customer-1', 'customer-2', 'customer-3'])->delete();
    }
};
