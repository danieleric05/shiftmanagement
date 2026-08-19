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
        Schema::table('servants', function (Blueprint $table) {
            $table->boolean('appele')->default(false)->after('statut');
            $table->boolean('whatsapp_1')->default(false)->after('appele');
            $table->boolean('whatsapp_2')->default(false)->after('whatsapp_1');
            $table->boolean('formation_1')->default(false)->after('whatsapp_2');
            $table->boolean('formation_2')->default(false)->after('formation_1');
            $table->boolean('formation_3')->default(false)->after('formation_2');
            $table->unsignedTinyInteger('niveau_technique')->nullable()->after('formation_3');
            $table->unsignedTinyInteger('niveau_anglais')->nullable()->after('niveau_technique');
            $table->string('jour_alternatif')->nullable()->after('niveau_anglais');
            $table->text('notes')->nullable()->after('jour_alternatif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servants', function (Blueprint $table) {
            $table->dropColumn([
                'appele', 'whatsapp_1', 'whatsapp_2',
                'formation_1', 'formation_2', 'formation_3',
                'niveau_technique', 'niveau_anglais', 'jour_alternatif', 'notes',
            ]);
        });
    }
};
