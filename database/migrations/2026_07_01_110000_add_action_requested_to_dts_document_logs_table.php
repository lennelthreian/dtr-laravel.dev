<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('dts_document_logs', function (Blueprint $table) {
            $table->string('action_requested', 100)->nullable()->after('notes');
        });
    }

    public function down()
    {
        Schema::table('dts_document_logs', function (Blueprint $table) {
            $table->dropColumn('action_requested');
        });
    }
};
