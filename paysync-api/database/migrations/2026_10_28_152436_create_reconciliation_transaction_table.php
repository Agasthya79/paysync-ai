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
        Schema::create('reconciliation_transaction', function (Blueprint $table) {
    $table->id();
    $table->foreignId('reconciliation_id')->constrained()->cascadeOnDelete();
    $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
    $table->unique(['reconciliation_id', 'transaction_id']);
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_transaction');
    }
};
