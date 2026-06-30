<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameBioIdToEmpCode extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('bio_id', 'emp_code');
        });

        Schema::table('dtr_users', function (Blueprint $table) {
            $table->renameColumn('bio_id', 'emp_code');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('emp_code', 'bio_id');
        });

        Schema::table('dtr_users', function (Blueprint $table) {
            $table->renameColumn('emp_code', 'bio_id');
        });
    }
}
