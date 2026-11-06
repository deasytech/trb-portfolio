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
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('type', 30);
            $table->string('direction', 6);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('description')->nullable();
            $table->nullableMorphs('related');
            $table->foreignId('reverses_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->timestamp('posted_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'posted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
    }
};
