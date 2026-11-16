<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->string('drive_url', 500)->nullable();
            $table->unsignedInteger('photo_count')->default(0);
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn(['drive_url', 'photo_count', 'notes']);
        });
    }
};
