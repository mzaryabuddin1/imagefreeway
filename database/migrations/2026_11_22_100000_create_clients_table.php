<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->unique();      // white-label custom domain
            $table->string('status', 20)->default('active');     // active | suspended | archived
            $table->string('app_name')->nullable();              // brand name shown in the UI
            $table->string('primary_color', 9)->nullable();
            $table->string('secondary_color', 9)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('footer_text')->nullable();
            $table->json('modules')->nullable();                 // add-on switches
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('id')->constrained('clients')->nullOnDelete();
            $table->string('role', 20)->default('user')->after('password');  // super_admin | client_admin | user
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('status', 20)->default('active')->after('role');  // active | inactive | archived
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();                    // before/after values
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['client_id', 'created_at']);
        });

        Schema::create('help_entries', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('section', 60);
            $table->string('title');
            $table->longText('body');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_entries');
        Schema::dropIfExists('audit_logs');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn(['role', 'phone', 'status', 'last_login_at', 'deleted_at']);
        });
        Schema::dropIfExists('clients');
    }
};
