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
        Schema::table('magic_login_tokens', function (Blueprint $table) {
            // Drop existing unique index on token_hash to replace with composite unique (email, token_hash)
            // Conventional index name created by Laravel for unique('token_hash') is:
            // magic_login_tokens_token_hash_unique
            $table->dropUnique('magic_login_tokens_token_hash_unique');

            // Add composite unique index
            $table->unique(['email', 'token_hash'], 'mlt_email_token_hash_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('magic_login_tokens', function (Blueprint $table) {
            // Drop the composite unique and restore the original unique on token_hash
            $table->dropUnique('mlt_email_token_hash_unique');
            $table->unique('token_hash');
        });
    }
};
