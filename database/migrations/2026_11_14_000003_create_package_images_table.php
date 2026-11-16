<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto kartu paket yang diunggah admin disimpan di database (base64),
     * karena filesystem Vercel tidak persisten.
     */
    public function up(): void
    {
        Schema::create('package_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->unique()->constrained('packages')->cascadeOnDelete();
            $table->string('mime', 100);
            $table->longText('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_images');
    }
};
