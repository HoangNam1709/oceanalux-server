<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

  public function __construct(public $bookingId, public $holdExpiresAt, public $status) {}

public function broadcastOn(): array
{
    // Bắn vào channel riêng của đơn hàng đó
    return [new PrivateChannel('booking.' . $this->bookingId)];
}
}
