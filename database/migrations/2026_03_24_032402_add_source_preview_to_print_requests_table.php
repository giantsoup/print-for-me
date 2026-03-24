<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_requests', function (Blueprint $table) {
            $table->json('source_preview')->nullable()->after('source_url');
            $table->timestamp('source_preview_fetched_at')->nullable()->after('source_preview');
            $table->timestamp('source_preview_failed_at')->nullable()->after('source_preview_fetched_at');
        });
    }

    public function down(): void
    {
        Schema::table('print_requests', function (Blueprint $table) {
            $table->dropColumn([
                'source_preview',
                'source_preview_fetched_at',
                'source_preview_failed_at',
            ]);
        });
    }
};
