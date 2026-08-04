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
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color');
            $table->timestamps();

            $table->unique(['board_id', 'name']);
        });

        Schema::create('card_label', function (Blueprint $table) {
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();

            $table->primary(['card_id', 'label_id']);
        });

        Schema::create('card_user', function (Blueprint $table) {
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['card_id', 'user_id']);
        });

        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('position', 20, 10)->default(0);
            $table->timestamps();

            $table->index(['card_id', 'position']);
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained()->cascadeOnDelete();
            $table->string('content');
            $table->boolean('is_completed')->default(false);
            $table->decimal('position', 20, 10)->default(0);
            $table->timestamps();

            $table->index(['checklist_id', 'position']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['card_id', 'created_at']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->index(['card_id', 'created_at']);
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['board_id', 'created_at']);
            $table->index(['card_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('checklists');
        Schema::dropIfExists('card_user');
        Schema::dropIfExists('card_label');
        Schema::dropIfExists('labels');
    }
};
