<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; 
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingExpired implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $bookingId;

    public function __construct($bookingId)
    {
        $this->bookingId = $bookingId;
    }

    // Tạo một kênh riêng biệt mang tên ID của đơn hàng đó
    public function broadcastOn(): array
    {
        return [
            new Channel('booking.' . $this->bookingId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'BookingExpired';
    }
}
