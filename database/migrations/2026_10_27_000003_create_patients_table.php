<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('second_last_name', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->char('sex', 1)->nullable();
            $table->text('curp')->nullable()->comment('Cifrado');
            $table->char('curp_hash', 64)->nullable()->unique()->comment('HMAC-SHA256 para búsqueda exacta');
            $table->string('phone', 20)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('blood_type', 3)->nullable();

            // Contacto de emergencia
            $table->string('emergency_name', 150)->nullable();
            $table->string('emergency_phone', 20)->nullable();
            $table->string('emergency_relationship', 50)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'second_last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
