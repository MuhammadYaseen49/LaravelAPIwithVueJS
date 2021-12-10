<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = "users";
    
    protected $fillable = [
        'name',
        'email',
        'password',
        'age',
        'profile_picture',
        'Verification_Token',
        'Email_Verified_At',
        'PasswordReset_Token'
    ];

    public $timestamps = false;

    // protected $hidden = [
    //     'password',
    //     'remember_token',
    // ];

   
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
