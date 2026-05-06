<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gis_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('token');
            $table->string('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gis_tokens');
    }
};
