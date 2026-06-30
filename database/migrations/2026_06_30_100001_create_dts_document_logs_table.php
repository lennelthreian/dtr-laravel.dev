<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDtsDocumentLogsTable extends Migration
{
    public function up()
    {
        Schema::create('dts_document_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('dts_documents')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->text('notes')->nullable();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dts_document_logs');
    }
}
