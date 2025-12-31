<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peer_exchanges', function (Blueprint $table) {
            $table->id();
            // Link to the user who created the post (offer or request)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Type of post: 'offer' (I can teach) or 'request' (I need help)
            $table->enum('type', ['offer', 'request']); 
            
            $table->string('title', 150);
            $table->text('description');
            
            // Key Skill being taught/requested (e.g., 'Laravel', 'UI/UX Design')
            $table->string('skill_tag'); 
            
            // Status: 'open', 'matched', 'completed', 'closed'
            $table->enum('status', ['open', 'matched', 'completed', 'closed'])->default('open');
            
            // User who accepted the match (the peer)
            $table->foreignId('matched_user_id')->nullable()->constrained('users');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peer_exchanges');
    }
};