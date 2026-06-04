<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lost_items', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->primary();
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->unsignedBigInteger('loser_id');
            $table->foreign('loser_id')->references('user_id')->on('losers')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_items');
    }
};
