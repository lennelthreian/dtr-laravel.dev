<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChatThreadClosuresTable extends Migration
{
    public function up()
    {
        Schema::create('chat_thread_closures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thread_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['thread_id', 'user_id']);
            $table->foreign('thread_id')->references('id')->on('chat_threads')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_thread_closures');
    }
}
