<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Workflows become node graphs (nodes + connections stored as JSON); linear stages are retired.
        Schema::table('workflow_items', function (Blueprint $table) {
            $table->dropForeign(['current_stage_id']);
            $table->dropColumn('current_stage_id');
        });
        Schema::table('workflow_events', function (Blueprint $table) {
            $table->dropForeign(['stage_id']);
            $table->dropColumn('stage_id');
        });
        Schema::dropIfExists('workflow_stages');

        Schema::table('workflows', function (Blueprint $table) {
            $table->json('graph')->nullable()->after('name');
            $table->string('description', 300)->nullable()->after('name');
        });
        Schema::table('workflow_items', function (Blueprint $table) {
            $table->string('node_id', 40)->nullable()->after('document_id');
            $table->json('data')->nullable();
            $table->string('result', 120)->nullable();
        });
        Schema::table('workflow_events', function (Blueprint $table) {
            $table->string('node_id', 40)->nullable()->after('item_id');
            $table->string('node_name', 120)->nullable();
            $table->string('outcome', 120)->nullable();
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->string('link_field', 80)->nullable();   // index field that ties documents together (e.g. contract_number)
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->string('doc_type', 100)->nullable()->index();
            $table->string('email_from', 190)->nullable();
        });

        Schema::create('doc_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('email_alias', 80)->nullable()->unique();      // e.g. wdg.invoices -> wdg.invoices@<inbound domain>
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->string('index_field', 80)->nullable();                // where the number found in the subject is stored
            $table->string('subject_pattern', 200)->nullable();           // regex with one group; blank = built-in
            $table->timestamps();
            $table->unique(['client_id', 'name']);
        });

        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 300)->nullable();
            $table->json('fields')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('workflow_items')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('signature_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('workflow_items')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('node_id', 40)->nullable();
            $table->string('signer_name', 120);
            $table->string('signer_email', 190);
            $table->string('message', 500)->nullable();
            $table->string('token', 64)->unique();
            $table->string('status', 20)->default('pending');   // pending, signed, declined, cancelled
            $table->timestamp('expires_at');
            $table->timestamp('signed_at')->nullable();
            $table->longText('signature')->nullable();           // PNG data URL
            $table->string('typed_name', 120)->nullable();
            $table->string('doc_hash', 64)->nullable();          // sha256 of the file that was signed
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->string('decline_reason', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_requests');
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('forms');
        Schema::dropIfExists('doc_types');
    }
};
