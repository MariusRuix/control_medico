<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cie10_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('description', 500);
            $table->string('chapter', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->fullText('description');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cie10_codes');
    }
};
