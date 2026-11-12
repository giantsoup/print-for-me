<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Future 3MF import may add nullable source references; manual plates need none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_queue_plates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('print_queue_item_id')->constrained('print_queue_items')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('position');
            $table->string('status', 16)->default('queued')->index();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->string('filament', 160)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('printer_slot')->nullable()->unique();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['print_queue_item_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_queue_plates');
    }
};
