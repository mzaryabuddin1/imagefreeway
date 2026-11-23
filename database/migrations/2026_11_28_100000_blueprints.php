<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blueprints', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('industry', 80)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->text('notes')->nullable();
            $table->json('data');
            $table->foreignId('source_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['name', 'version']);
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('blueprint_id')->nullable()->constrained('blueprints')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('blueprint_id');
        });
        Schema::dropIfExists('blueprints');
    }
};
