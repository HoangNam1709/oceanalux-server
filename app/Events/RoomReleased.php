<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // Dùng bản "Now" để chạy ngay lập tức
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Sửa ShouldBroadcast thành ShouldBroadcastNow để không cần chạy queue:work
class RoomReleased implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $cabinClassId;
    public $availableRooms;

    /**
     * Create a new event instance.
     */
    public function __construct($cabinClassId, $availableRooms)
    {
        $this->cabinClassId = $cabinClassId;
        $this->availableRooms = $availableRooms;
    }

    /**
     * Tên Channel để React lắng nghe (rooms)
     */
    public function broadcastOn(): array
    {
        return [new Channel('rooms')];
    }

    /**
     * Ép tên Event gọn gàng để React dễ bắt (Không bị dính namespace App\Events)
     */
    public function broadcastAs(): string
    {
        return 'RoomReleased';
    }
}