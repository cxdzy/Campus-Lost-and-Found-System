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
        Schema::create('ai_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('found_item_id');
            $table->foreign('found_item_id')
                  ->references('item_id')
                  ->on('found_items')
                  ->cascadeOnDelete();
            $table->string('tag_name');
            $table->float('confidence_level');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_tags');
    }
};
