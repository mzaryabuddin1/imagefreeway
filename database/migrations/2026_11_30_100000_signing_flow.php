<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signature_requests', function (Blueprint $t) {
            $t->string('role', 60)->nullable()->after('signer_email');            // which "Sign here" boxes are theirs
            $t->unsignedSmallInteger('seq')->default(0)->after('role');           // 0 = sent at once, 1.. = signing order
            $t->foreignId('signer_user_id')->nullable()->after('seq')->constrained('users')->nullOnDelete();
            $t->timestamp('activated_at')->nullable();                            // when the link was sent
            $t->timestamp('first_viewed_at')->nullable();
            $t->timestamp('last_viewed_at')->nullable();
            $t->unsignedInteger('view_count')->default(0);
            $t->timestamp('reminded_at')->nullable();
            $t->unsignedTinyInteger('reminder_count')->default(0);
            $t->unsignedTinyInteger('remind_after_days')->default(2);
            $t->string('method', 12)->nullable();                                 // draw, type, upload, camera
        });
        // Requests that existed before were sent the moment they were made
        \DB::table('signature_requests')->update(['activated_at' => \DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('signature_requests', function (Blueprint $t) {
            $t->dropConstrainedForeignId('signer_user_id');
            $t->dropColumn(['role', 'seq', 'activated_at', 'first_viewed_at', 'last_viewed_at', 'view_count', 'reminded_at', 'reminder_count', 'remind_after_days', 'method']);
        });
    }
};
