<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDtsDocumentsTable extends Migration
{
    public function up()
    {
        Schema::create('dts_documents', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number', 50)->unique();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('type', 50)->default('incoming');
            $table->string('status', 50)->default('pending');
            $table->string('priority', 20)->default('normal');
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_received')->nullable();
            $table->date('date_actioned')->nullable();
            $table->date('deadline')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dts_documents');
    }
}
