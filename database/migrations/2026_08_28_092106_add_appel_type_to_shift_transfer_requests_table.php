<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE shift_transfer_requests MODIFY COLUMN type ENUM('releve', 'permutation', 'appel') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE shift_transfer_requests MODIFY COLUMN type ENUM('releve', 'permutation') NOT NULL");
    }
};
