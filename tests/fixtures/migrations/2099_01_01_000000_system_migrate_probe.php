<?php

use Illuminate\Database\Migrations\Migration;

// Migration factice (sans DDL) utilisée par SystemMigrateTest pour vérifier
// que POST /system/migrate signale une migration appliquée.
return new class extends Migration
{
    public function up(): void {}

    public function down(): void {}
};
