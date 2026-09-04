<?php

namespace App\Events;

use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PvpShotFired implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Room $room;
    public array $shotData;

    public function __construct(Room $room, array $shotData)
    {
        $this->room = $room;
        $this->shotData = $shotData;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('room.' . $this->room->room_code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pvp.shot.fired';
    }
}