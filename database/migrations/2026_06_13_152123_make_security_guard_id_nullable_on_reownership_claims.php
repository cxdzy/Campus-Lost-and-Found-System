<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the NOT NULL constraint; the FK itself stays valid for non-null values.
        DB::statement('ALTER TABLE reownership_claims ALTER COLUMN security_guard_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reownership_claims ALTER COLUMN security_guard_id SET NOT NULL');
    }
};
