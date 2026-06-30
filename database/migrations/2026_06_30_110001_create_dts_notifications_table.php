<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDtsNotificationsTable extends Migration
{
    public function up()
    {
        Schema::create('dts_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('dts_documents')->nullOnDelete();
            $table->string('type', 50);
            $table->string('message', 500);
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dts_notifications');
    }
}
