<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table): void {
            $table->id();
            $table->string('generic_name', 150)->index();
            $table->string('brand_name', 150)->nullable()->index();
            $table->string('presentation', 150)->nullable()->comment('Tabletas, jarabe, ampolleta…');
            $table->string('concentration', 100)->nullable();
            $table->string('route', 50)->nullable()->comment('Vía de administración');

            // Vademécum personal
            $table->boolean('is_favorite')->default(false)->index();
            $table->string('default_dose', 100)->nullable();
            $table->string('default_frequency', 100)->nullable();
            $table->string('default_duration', 100)->nullable();
            $table->string('default_instructions', 500)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medications');
    }
};
