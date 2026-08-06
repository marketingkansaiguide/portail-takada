<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'general_cancellation_policy',
        'admin_email_notifications', // 💡 NOUVEAU
        'chat_reminder_hours',       // 💡 NOUVEAU
        'train_ticket_suppliers' => 'array',
    ];
}