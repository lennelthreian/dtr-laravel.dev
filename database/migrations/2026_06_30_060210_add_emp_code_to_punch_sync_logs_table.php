<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmpCodeToPunchSyncLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('punch_sync_logs', function (Blueprint $table) {
            $table->string('emp_code', 50)->nullable()->after('bio_id');
        });
    }

    public function down()
    {
        Schema::table('punch_sync_logs', function (Blueprint $table) {
            $table->dropColumn('emp_code');
        });
    }
}
