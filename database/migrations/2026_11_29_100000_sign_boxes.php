<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annotations', function (Blueprint $t) {
            $t->unsignedBigInteger('signature_request_id')->nullable()->after('document_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('annotations', fn (Blueprint $t) => $t->dropColumn('signature_request_id'));
    }
};
