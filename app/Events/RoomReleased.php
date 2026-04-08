<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; 
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomReleased implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $cabinClassId;
    public $availableRooms;
    public $scheduleId; 

    public function __construct($cabinClassId, $availableRooms, $scheduleId) 
    {
        $this->cabinClassId = $cabinClassId;
        $this->availableRooms = $availableRooms;
        $this->scheduleId = $scheduleId; // Gán giá trị
    }

    public function broadcastOn(): array
    {
        return [new Channel('rooms')];
    }


    public function broadcastAs(): string
    {
        return 'RoomReleased';
    }

    public function broadcastWith(): array
    {
        return [
            'cabinClassId' => $this->cabinClassId,
            'availableRooms' => $this->availableRooms,
            'schedule_id'  => $this->scheduleId, // Gửi cái này xuống để React kiểm tra
        ];
    }
}