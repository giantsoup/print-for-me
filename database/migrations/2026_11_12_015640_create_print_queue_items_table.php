<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_queue_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('print_request_id')->nullable()->constrained('print_requests')->nullOnDelete();
            $table->string('title', 160);
            $table->string('for_whom', 120)->nullable();
            $table->date('needed_by_date')->nullable()->index();
            $table->string('filament', 160)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('position')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();
            $table->index(['completed_at', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_queue_items');
    }
};
