<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class WebhookEvent extends Model
{
    protected $fillable = ['gateway', 'event_id'];

    /**
     * Record an event id; returns false if it was already processed.
     */
    public static function firstDelivery(string $gateway, string $eventId): bool
    {
        try {
            static::create(['gateway' => $gateway, 'event_id' => $eventId]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
