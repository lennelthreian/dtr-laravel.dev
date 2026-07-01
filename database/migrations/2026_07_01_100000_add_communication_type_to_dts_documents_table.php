<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCommunicationTypeToDtsDocumentsTable extends Migration
{
    public function up()
    {
        Schema::table('dts_documents', function (Blueprint $table) {
            $table->string('communication_type', 20)->default('internal')->after('type');
        });
    }

    public function down()
    {
        Schema::table('dts_documents', function (Blueprint $table) {
            $table->dropColumn('communication_type');
        });
    }
}
