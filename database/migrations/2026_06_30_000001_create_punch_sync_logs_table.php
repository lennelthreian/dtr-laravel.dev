<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePunchSyncLogsTable extends Migration
{
    public function up()
    {
        Schema::create('punch_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('bio_id', 50)->nullable();
            $table->dateTime('punch_time')->nullable();
            $table->string('punch_state', 20)->nullable();
            $table->string('terminal_sn', 50)->nullable();
            $table->string('terminal_alias', 100)->nullable();
            $table->string('source', 50)->nullable();
            $table->string('sync_status', 20)->default('pending');
            $table->text('sync_message')->nullable();
            $table->timestamps();

            $table->index('sync_status');
            $table->index('punch_time');
        });

        Schema::create('sync_progress', function (Blueprint $table) {
            $table->id();
            $table->string('sync_type', 50)->unique();
            $table->bigInteger('last_synced_id')->default(0);
            $table->dateTime('last_sync_at')->nullable();
            $table->timestamps();
        });

        DB::table('sync_progress')->insert([
            ['sync_type' => 'iclock_transaction', 'last_synced_id' => 0, 'last_sync_at' => null],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('punch_sync_logs');
        Schema::dropIfExists('sync_progress');
    }
}
