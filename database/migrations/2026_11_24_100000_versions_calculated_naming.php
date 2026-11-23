<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_fields', function (Blueprint $table) {
            $table->string('formula', 200)->nullable();
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->string('naming_pattern', 200)->nullable();
        });
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('path');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 300)->nullable();
            $table->timestamp('replaced_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
