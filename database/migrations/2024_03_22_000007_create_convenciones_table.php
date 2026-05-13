<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convenciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convenciones');
    }
};
