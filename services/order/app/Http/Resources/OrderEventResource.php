<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\Query\ListOrderEvents\OrderEventItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderEventItem
 */
final class OrderEventResource extends JsonResource
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    /**
     * Deliberately excludes actor_id and payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'order_item_id' => $this->orderItemId,
            'actor_role' => $this->actorRole,
            'occurred_at' => $this->occurredAt->utc()->format(self::DATE_FORMAT),
        ];
    }
}
