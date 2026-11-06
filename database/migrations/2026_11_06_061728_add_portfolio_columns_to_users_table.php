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
            $table->string('customer_reference', 20)->nullable()->unique()->after('id');
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('country', 2)->nullable()->after('phone');
            $table->string('status', 20)->default('active')->index()->after('password');
            $table->timestamp('last_login_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['customer_reference', 'phone', 'country', 'status', 'last_login_at']);
        });
    }
};
