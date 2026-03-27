<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomReleased implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $cabinClassId;
    public $availableRooms;

    public function __construct($cabinClassId, $availableRooms)
    {
        $this->cabinClassId = $cabinClassId;
        $this->availableRooms = $availableRooms;
    }

    // Phát trên kênh công khai để ai cũng nhận được
    public function broadcastOn(): array
    {
        return [new Channel('rooms')];
    }
}