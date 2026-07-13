<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRecipientIdToDtsDocumentLogsTable extends Migration
{
    public function up()
    {
        Schema::table('dts_document_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('recipient_id')->nullable()->after('action_requested');
            $table->foreign('recipient_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('dts_document_logs', function (Blueprint $table) {
            $table->dropForeign(['recipient_id']);
            $table->dropColumn('recipient_id');
        });
    }
}
