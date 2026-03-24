<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('print_request_completion_photos')) {
            Schema::create('print_request_completion_photos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('print_request_id');
                $table->string('disk');
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size_bytes');
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('sha256', 64);
                $table->timestamps();
            });
        }

        $this->ensureIndexesAndForeignKey();
    }

    public function down(): void
    {
        Schema::dropIfExists('print_request_completion_photos');
    }

    private function ensureIndexesAndForeignKey(): void
    {
        if (! Schema::hasIndex('print_request_completion_photos', ['sha256'])) {
            Schema::table('print_request_completion_photos', function (Blueprint $table) {
                $table->index('sha256', 'prcp_sha256_idx');
            });
        }

        if (! Schema::hasIndex('print_request_completion_photos', ['print_request_id', 'sort_order'])) {
            Schema::table('print_request_completion_photos', function (Blueprint $table) {
                $table->index(['print_request_id', 'sort_order'], 'prcp_request_sort_idx');
            });
        }

        if ($this->hasPrintRequestForeignKey()) {
            return;
        }

        Schema::table('print_request_completion_photos', function (Blueprint $table) {
            $table->foreign('print_request_id', 'prcp_print_request_fk')
                ->references('id')
                ->on('print_requests')
                ->cascadeOnDelete();
        });
    }

    private function hasPrintRequestForeignKey(): bool
    {
        return collect(Schema::getForeignKeys('print_request_completion_photos'))
            ->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === ['print_request_id']);
    }
};
