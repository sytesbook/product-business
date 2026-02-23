<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->string('uid', 64)->primary();
            $table->string('status', 64)->nullable(false);
            $table->string('title')->nullable(false);
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->string('uid', 64)->primary();
            $table->string('domain')->nullable(false)->unique();
            $table->boolean('is_primary')->nullable(false);
            $table->string('site_uid', 64)->nullable(false);
            $table->index(['site_uid'], 'IDX_8C7BBF9DA7063726');
            $table->foreign('site_uid')->references('uid')->on('sites')->cascadeOnDelete();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->string('uid', 64)->primary();
            $table->string('title')->nullable(false);
            $table->string('path')->nullable(false);
            $table->string('site_uid', 64)->nullable(false);
            $table->foreign('site_uid')->references('uid')->on('sites')->cascadeOnDelete();
            $table->unique(['site_uid', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('sites');
    }
};
