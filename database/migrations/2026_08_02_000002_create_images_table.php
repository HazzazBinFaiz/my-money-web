<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            // Nullable: system images anyone may use, nobody may edit or delete.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->tinyInteger('type');
            $table->string('image_name');
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
