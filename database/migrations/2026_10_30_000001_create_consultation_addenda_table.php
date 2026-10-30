<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas aclaratorias (addenda): correcciones o información adicional a una
 * nota ya firmada. No se editan ni se borran (NOM-004-SSA3-2012).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_addenda', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->restrictOnDelete();
            $table->text('body')->comment('Cifrado');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['consultation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_addenda');
    }
};
