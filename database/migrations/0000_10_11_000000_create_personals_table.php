<?php

use App\Enums\TipoDocumento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('tipo_documento', array_column(TipoDocumento::cases(), 'value'));
            $table->string('numero_documento')->unique();
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('telefono')->nullable();
            $table->string('cargo')->nullable();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personals');
    }
};
