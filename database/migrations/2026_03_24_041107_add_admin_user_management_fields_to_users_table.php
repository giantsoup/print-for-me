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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'access_revoked_at')) {
                $table->timestamp('access_revoked_at')->nullable()->after('whitelisted_at');
            }

            if (! Schema::hasColumn('users', 'access_revoked_by')) {
                $table->foreignId('access_revoked_by')
                    ->nullable()
                    ->after('access_revoked_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->after('session_version');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'access_revoked_by')) {
                $table->dropConstrainedForeignId('access_revoked_by');
            }

            if (Schema::hasColumn('users', 'access_revoked_at')) {
                $table->dropColumn('access_revoked_at');
            }

            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
