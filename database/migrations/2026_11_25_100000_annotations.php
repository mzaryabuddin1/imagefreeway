<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 12); // note, highlight, redact
            $table->unsignedSmallInteger('page')->default(1);
            $table->decimal('x', 7, 6);
            $table->decimal('y', 7, 6);
            $table->decimal('w', 7, 6);
            $table->decimal('h', 7, 6);
            $table->string('text', 500)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['document_id', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annotations');
    }
};
