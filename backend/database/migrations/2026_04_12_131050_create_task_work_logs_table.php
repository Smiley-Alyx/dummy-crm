<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->unsignedInteger('minutes');
            $table->string('comment')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'user_id', 'work_date']);
            $table->index(['task_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_work_logs');
    }
};
