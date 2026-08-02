<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            // Owner for now; book level access control will hang off this table.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Images are user level and created later in the migration order,
            // so this stays a plain reference.
            $table->unsignedBigInteger('icon_id')->nullable();
            $table->boolean('is_default')->default(false);

            // Display preferences, applied by Util::displayAmount().
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->string('currency', 8)->nullable();
            $table->tinyInteger('currency_position')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
