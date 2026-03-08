<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_template_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->string('name');
            $table->string('assignee_type')->default('role');
            $table->string('assignee_role')->nullable();
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('fallback_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('due_in_hours')->nullable();
            $table->timestamps();

            $table->unique(['workflow_template_id', 'step_order']);
            $table->index(['workflow_template_id', 'assignee_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_template_steps');
    }
};
