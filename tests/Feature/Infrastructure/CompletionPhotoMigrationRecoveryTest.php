<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('recovers when the completion photos table already exists from a partial migration run', function () {
    Schema::dropIfExists('print_request_completion_photos');

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

    $migration = require database_path('migrations/2026_03_24_213101_create_print_request_completion_photos_table.php');
    $migration->up();

    expect(Schema::hasIndex('print_request_completion_photos', ['sha256']))->toBeTrue();
    expect(Schema::hasIndex('print_request_completion_photos', ['print_request_id', 'sort_order']))->toBeTrue();
    expect(collect(Schema::getForeignKeys('print_request_completion_photos'))
        ->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === ['print_request_id']))->toBeTrue();
});
