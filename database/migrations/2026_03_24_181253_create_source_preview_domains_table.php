<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('source_preview_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('label');
            $table->string('policy')->default('allow');
            $table->foreignId('last_seen_print_request_id')->nullable()->constrained('print_requests')->nullOnDelete();
            $table->text('last_seen_url')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->string('last_attempt_status')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamps();

            $table->index(['policy', 'last_seen_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_preview_domains');
    }
};
