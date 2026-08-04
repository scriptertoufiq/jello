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
        Schema::create('board_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('position', 20, 10)->default(0);
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();

            $table->index(['board_id', 'position']);
        });

        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_list_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('position', 20, 10)->default(0);
            $table->date('start_date')->nullable();
            $table->timestamp('due_date')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->string('cover_color')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['board_list_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
        Schema::dropIfExists('board_lists');
    }
};
