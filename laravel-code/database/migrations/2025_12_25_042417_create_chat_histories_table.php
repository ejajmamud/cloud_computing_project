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
        Schema::create('chat_histories', function (Blueprint $table) {
            $table->id();
            // Link chat to a specific student
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // 'user' (student) or 'ai' (the tutor)
            $table->string('role'); 
            // The actual message text
            $table->longText('content'); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_histories');
    }
};
