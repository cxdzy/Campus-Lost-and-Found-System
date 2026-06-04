<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('found_items', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->primary();
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            // Nullable: Telegram-bot submissions may not have a registered finder account
            $table->unsignedBigInteger('finder_id')->nullable();
            $table->foreign('finder_id')->references('user_id')->on('finders')->nullOnDelete();
            $table->string('image_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('found_items');
    }
};
