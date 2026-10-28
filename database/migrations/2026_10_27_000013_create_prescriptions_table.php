<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->restrictOnDelete();
            $table->string('folio', 30)->unique();
            $table->text('general_instructions')->nullable();
            $table->string('pdf_path')->nullable();
            $table->char('signature_hash', 64)->nullable()->comment('SHA-256 del contenido al emitir');
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
