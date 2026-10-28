<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_appointable')->default(false)->comment('Se puede agendar como cita');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
