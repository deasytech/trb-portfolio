<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store a hash of the original request so a reused idempotency key with different details can be rejected.
     */
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->string('request_fingerprint', 64)->nullable()->after('idempotency_key');
        });
    }

    /**
     * Remove the fingerprint column.
     */
    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn('request_fingerprint');
        });
    }
};
