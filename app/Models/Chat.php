<?php
// app/Models/Chat.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    protected $fillable = ['user_id', 'admin_id', 'is_closed'];


    public function user() {
        return $this->belongsTo(User::class);
    }


    public function admin() {
        return $this->belongsTo(User::class, 'admin_id');
    }
    public function messages() {
        return $this->hasMany(Message::class);
    }
}
