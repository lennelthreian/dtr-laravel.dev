<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCategoryToDtsDocumentsTable extends Migration
{
    public function up()
    {
        Schema::table('dts_documents', function (Blueprint $table) {
            $table->string('category', 50)->nullable()->after('title');
        });
    }

    public function down()
    {
        Schema::table('dts_documents', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
}
