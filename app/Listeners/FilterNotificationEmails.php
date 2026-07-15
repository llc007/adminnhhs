<?php

namespace App\Listeners;

use App\Models\School;
use Illuminate\Notifications\Events\NotificationSending;

class FilterNotificationEmails
{
    /**
     * Handle the event.
     */
    public function handle(NotificationSending $event): ?bool
    {
        // Only intercept the mail channel
        if ($event->channel !== 'mail') {
            return null;
        }

        $schoolId = null;

        // Try to get the school ID from the notification's related models dynamically
        foreach (get_object_vars($event->notification) as $property) {
            if (is_object($property) && isset($property->school_id)) {
                $schoolId = $property->school_id;
                break;
            }
        }

        // Fallback to the notifiable's current school (if it's a User)
        if (! $schoolId && method_exists($event->notifiable, 'currentSchool')) {
            $schoolId = $event->notifiable->current_school_id ?? null;
        }

        if ($schoolId) {
            $school = School::find($schoolId);
            if ($school && ! $school->emailsEnabled()) {
                // Return false to cancel the notification on the mail channel
                return false;
            }
        }

        return null;
    }
}
