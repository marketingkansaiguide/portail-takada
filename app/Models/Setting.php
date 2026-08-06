<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'general_cancellation_policy',
        'admin_email_notifications', 
        'chat_reminder_hours',       
        'train_ticket_suppliers',
    ];

    protected $casts = [
        'admin_email_notifications' => 'array',
        'train_ticket_suppliers' => 'array',
    ];
}