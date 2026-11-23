<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- Workflow engine (F-WF-01..04) ----
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('auto_start')->default(false);   // start when a document becomes indexed
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('approval');   // approval | task
            $table->json('conditions')->nullable();            // data only, never executed: [{field,op,value}]
            $table->json('assignees')->nullable();            // user ids; empty = Client Admins
            $table->unsignedSmallInteger('overdue_days')->default(3);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('current_stage_id')->nullable()->constrained('workflow_stages')->nullOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active');   // active | complete
            $table->timestamp('stage_entered_at')->nullable();
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'status']);
        });

        Schema::create('workflow_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('workflow_items')->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('workflow_stages')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);     // started | approved | rejected | completed | skipped | escalated
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // ---- Compliance and records (F-COMP-01..03) ----
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedSmallInteger('retention_years')->nullable()->after('ocr_enabled');
            $table->unsignedBigInteger('retention_field_id')->nullable()->after('retention_years');  // date field the clock starts from; null = upload date
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->date('disposal_date')->nullable()->after('indexed_at');
            $table->boolean('legal_hold')->default(false)->after('disposal_date');
            $table->string('legal_hold_reason')->nullable()->after('legal_hold');
            $table->index(['client_id', 'disposal_date']);
        });

        Schema::create('retention_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('project_name');
            $table->string('document_name');
            $table->unsignedBigInteger('document_id')->nullable();
            $table->json('index_values')->nullable();
            $table->date('disposal_date')->nullable();
            $table->timestamp('destroyed_at')->useCurrent();
        });

        // ---- Secure sharing links (F-SHR-01) ----
        Schema::create('share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->string('code_hash')->nullable();
            $table->boolean('allow_download')->default(true);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('opened_count')->default(0);
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_links');
        Schema::dropIfExists('retention_certificates');
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'disposal_date']);
            $table->dropColumn(['disposal_date', 'legal_hold', 'legal_hold_reason']);
        });
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn(['retention_years', 'retention_field_id']));
        Schema::dropIfExists('workflow_events');
        Schema::dropIfExists('workflow_items');
        Schema::dropIfExists('workflow_stages');
        Schema::dropIfExists('workflows');
    }
};
