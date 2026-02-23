<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->string('uid')->primary();
            $table->string('status');
            $table->string('title');
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->string('uid')->primary();
            $table->string('domain')->unique();
            $table->boolean('is_primary');
            $table->string('site_uid');
            $table->foreign('site_uid')->references('uid')->on('sites');
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->string('uid')->primary();
            $table->string('title');
            $table->string('path');
            $table->string('site_uid');
            $table->foreign('site_uid')->references('uid')->on('sites');
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
