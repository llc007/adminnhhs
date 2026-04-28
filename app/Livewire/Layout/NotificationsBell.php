<?php

namespace App\Livewire\Layout;

use Livewire\Component;

class NotificationsBell extends Component
{
    public function markAsRead($id, $url = null)
    {
        $notification = auth()->user()->notifications()->find($id);
        if ($notification) {
            $notification->markAsRead();
        }
        
        if ($url && $url !== '#') {
            return redirect()->to($url);
        }
    }

    public function render()
    {
        return view('livewire.layout.notifications-bell', [
            'notifications' => auth()->user()->unreadNotifications
        ]);
    }
}
