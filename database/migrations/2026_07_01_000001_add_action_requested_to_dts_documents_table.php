<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddActionRequestedToDtsDocumentsTable extends Migration
{
    public function up()
    {
        Schema::table('dts_documents', function (Blueprint $table) {
            $table->string('action_requested', 100)->nullable()->after('category');
        });
    }

    public function down()
    {
        Schema::table('dts_documents', function (Blueprint $table) {
            $table->dropColumn('action_requested');
        });
    }
}
