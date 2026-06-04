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
        Schema::create('reownership_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('found_item_id');
            $table->foreign('found_item_id')
                  ->references('item_id')
                  ->on('found_items')
                  ->cascadeOnDelete();
            $table->unsignedBigInteger('loser_id');
            $table->foreign('loser_id')
                  ->references('user_id')
                  ->on('losers')
                  ->cascadeOnDelete();
            $table->foreignId('security_guard_id')->constrained('users');
            $table->string('otp_code');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reownership_claims');
    }
};
