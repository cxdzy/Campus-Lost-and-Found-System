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
            $table->foreignId('found_item_id')->constrained('found_items');
            $table->foreignId('loser_id')->constrained('losers', 'user_id');
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
