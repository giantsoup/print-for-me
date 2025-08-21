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
            // Add a composite unique index for (email, token_hash) to harden lookups and prevent duplicates.
            $table->unique(['email', 'token_hash'], 'magic_login_tokens_email_token_hash_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('magic_login_tokens', function (Blueprint $table) {
            $table->dropUnique('magic_login_tokens_email_token_hash_unique');
        });
    }
};
