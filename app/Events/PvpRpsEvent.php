<?php

namespace App\Events;

use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PvpRpsEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Room $room;
    public array $rpsData;

    public function __construct(Room $room, array $rpsData)
    {
        $this->room = $room;
        $this->rpsData = $rpsData;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('room.' . $this->room->room_code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pvp.rps.event';
    }
}