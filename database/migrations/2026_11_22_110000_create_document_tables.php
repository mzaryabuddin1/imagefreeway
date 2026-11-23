<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('ocr_enabled')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Index fields are defined per project (F-IDX-01)
        Schema::create('project_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('key', 60);                      // stable slug used as the JSON key
            $table->string('type', 20)->default('text');    // text|number|amount|date|yesno|picklist
            $table->boolean('required')->default(false);
            $table->boolean('searchable')->default(true);
            $table->boolean('show_in_results')->default(true);
            $table->boolean('read_only')->default(false);
            $table->json('options')->nullable();            // permitted values for picklists
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();                          // deleting hides a field but keeps stored values (F-IDX-02)
            $table->unique(['project_id', 'key']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('indexed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('path');
            $table->string('status', 20)->default('unindexed');   // unindexed | indexed
            $table->json('index_values')->nullable();
            $table->longText('search_text')->nullable();          // index values flattened for global search
            $table->longText('ocr_text')->nullable();
            $table->string('ocr_status', 20)->default('none');    // none|done|failed|unavailable
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['client_id', 'project_id', 'status']);
        });

        // Per-project rights for ordinary users (admins have everything inside their client)
        Schema::create('project_rights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_download')->default(false);
            $table->boolean('can_upload')->default(false);
            $table->boolean('can_index')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_rights');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('project_fields');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('departments');
    }
};
