<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PeerExchange extends Model
{
    use HasFactory;

    protected $table = 'peer_exchanges'; // Optional, but good for clarity
    
    // Allow mass assignment for these fields (matching migration columns)
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'description',
        'skill_tag',
        'status',
        'matched_user_id',
    ];

    // RELATIONSHIPS
    
    // The user who created the post (offer or request)
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // The user who accepted the match (the peer/tutor)
    public function matchedPeer()
    {
        return $this->belongsTo(User::class, 'matched_user_id');
    }
}