<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NUM_SITES = 50;
    private const PAGES_PER_SITE = 10;

    public function up(): void
    {
        $sites = [];
        $domains = [];
        $pages = [];

        for ($i = 1; $i <= self::NUM_SITES; $i++) {
            $siteUid = sprintf('test-site-%04d', $i);
            $primaryDomain = sprintf('test-site-%04d.example.com', $i);

            $sites[] = [
                'uid'    => $siteUid,
                'status' => 'active',
                'title'  => sprintf('Test Site %04d', $i),
            ];

            $domains[] = [
                'uid'        => sprintf('test-domain-%04d-primary', $i),
                'domain'     => $primaryDomain,
                'is_primary' => true,
                'site_uid'   => $siteUid,
            ];

            $domains[] = [
                'uid'        => sprintf('test-domain-%04d-www', $i),
                'domain'     => sprintf('www.test-site-%04d.example.com', $i),
                'is_primary' => false,
                'site_uid'   => $siteUid,
            ];

            for ($j = 1; $j <= self::PAGES_PER_SITE; $j++) {
                $pages[] = [
                    'uid'      => sprintf('test-page-%04d-%04d', $i, $j),
                    'title'    => sprintf('Page %04d of Site %04d', $j, $i),
                    'path'     => sprintf('/page-%04d', $j),
                    'site_uid' => $siteUid,
                ];
            }
        }

        DB::table('sites')->insert($sites);
        DB::table('domains')->insert($domains);
        DB::table('pages')->insert($pages);
    }

    public function down(): void
    {
        $siteUids = array_map(
            static fn(int $i) => sprintf('test-site-%04d', $i),
            range(1, self::NUM_SITES)
        );

        // Cascade deletes will remove associated domains and pages.
        DB::table('sites')->whereIn('uid', $siteUids)->delete();
    }
};
