<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_requests', function (Blueprint $table) {
            $table->date('needed_by_date')->nullable()->after('instructions');
            $table->index('needed_by_date');
        });
    }

    public function down(): void
    {
        Schema::table('print_requests', function (Blueprint $table) {
            $table->dropIndex(['needed_by_date']);
            $table->dropColumn('needed_by_date');
        });
    }
};
