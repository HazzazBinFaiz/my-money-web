<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('type');
            // Null for transfers, which carry no category.
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->bigInteger('amount')->default(0);
            $table->bigInteger('charge')->default(0);
            $table->foreignId('from_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('to_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            // Running balance of each side after this transaction. The unused
            // side stays 0 (income and expense only touch one account).
            $table->bigInteger('from_account_balance')->default(0);
            $table->bigInteger('to_account_balance')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
