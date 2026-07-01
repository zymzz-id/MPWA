<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('groq_api')->nullable()->after('dalle_api');
            $table->string('groq_name')->nullable()->after('groq_api');
            $table->string('deepseek_api')->nullable()->after('groq_name');
            $table->string('deepseek_name')->nullable()->after('deepseek_api');
        });
    }

    public function down(): void
    {
    }
};
