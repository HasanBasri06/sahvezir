<?php

namespace App\Http\Controllers;

use App\Events\InboxUnreadUpdated;
use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Requests;
use App\Models\Shipment;
use App\Services\MessagePushService;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    use ResponseTrait;

    public function create(Request $request)
    {
        $v = Validator::make($request->all(), [
            'shipment_id' => 'nullable|integer|exists:shipments,id',
            'sender_id'   => 'required|integer|exists:users,id',
            'receiver_id' => 'required|integer|exists:users,id',
            'message'     => 'required|string|max:155',
        ]);

        if ($v->fails()) {
            return $this->error([], 'Validation hatası', $v->errors(), 422);
        }

        $data = array_filter($v->validated(), fn ($v) => $v !== null && $v !== '');
        $data['type'] = 'normal';
        $message = Message::create($data);

        /** Alıcı `private user.{receiver_id}` — `unread_count` ile rozet; `toOthers` API isteğinde socket yokken gereksiz */
        broadcast(new MessageSent($message));

        try {
            app(MessagePushService::class)->notifyOnNormalMessage($message);
        } catch (\Throwable $e) {
            Log::error('push.message.notify_failed', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->success($message->toArray(), 'Mesaj oluşturuldu', 201);
    }

    public function getAllMessageBySenderAndReceiverId(Request $request)
    {
        $v = Validator::make($request->all(), [
            'user_id'   => 'required|integer|exists:users,id',
        ]);

        if ($v->fails()) {
            return $this->error([], 'Validation hatası', $v->errors(), 422);
        }

        $userId = (int) $request->input('user_id');

        $messages = Message::where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)
                    ->orWhere('receiver_id', $userId);
            })
            ->orderBy('created_at', 'asc')
            ->where('status', 'accepted')
            ->get();

        return $this->success($messages, 'OK', 200);
    }
    public function getAllMessageByUserId(Request $request) {
        $v = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($v->fails()) {
            return $this->error([], 'Validation hatası', $v->errors(), 422);
        }

        $userId = (int) $request->input('user_id');

        $messages = Message::with(['sender:id,first_name,last_name', 'receiver:id,first_name,last_name'])
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)
                ->orWhere('receiver_id', $userId);
            })
            ->where('status', 'accepted')
            ->with('shipment')
            ->orderBy('created_at', 'desc')
            ->get();

        /** İlan + araç sahibi teklifi — mobil mesaj listesinde rota ile yan yana gösterim */
        $bidderPairs = [];
        foreach ($messages as $m) {
            if (! $m->shipment_id || ! $m->shipment) {
                continue;
            }
            $otherId = (int) $m->sender_id === $userId ? (int) $m->receiver_id : (int) $m->sender_id;
            $creater = (int) ($m->shipment->creater_id ?? 0);
            $bidderId = $creater === $userId ? $otherId : $userId;
            $key = (int) $m->shipment_id.':'.$bidderId;
            $bidderPairs[$key] = ['shipment_id' => (int) $m->shipment_id, 'user_id' => $bidderId];
        }

        $requestMap = [];
        if ($bidderPairs !== []) {
            $shipmentIds = collect($bidderPairs)->pluck('shipment_id')->unique()->values()->all();
            $allReqs = Requests::query()
                ->with('car')
                ->whereIn('shipment_id', $shipmentIds)
                ->orderByDesc('id')
                ->get();
            foreach ($allReqs as $req) {
                $k = (int) $req->shipment_id.':'.(int) $req->user_id;
                if (! isset($bidderPairs[$k])) {
                    continue;
                }
                if (! isset($requestMap[$k])) {
                    $requestMap[$k] = $req;
                }
            }
        }

        $payload = $messages->map(function (Message $m) use ($userId, $requestMap) {
            $data = $m->toArray();
            if ($m->shipment_id && $m->shipment) {
                $otherId = (int) $m->sender_id === $userId ? (int) $m->receiver_id : (int) $m->sender_id;
                $creater = (int) ($m->shipment->creater_id ?? 0);
                $bidderId = $creater === $userId ? $otherId : $userId;
                $key = (int) $m->shipment_id.':'.$bidderId;
                if (isset($requestMap[$key])) {
                    $r = $requestMap[$key];
                    $attrs = $r->getAttributes();
                    $data['conversation_request'] = [
                        'id' => $r->id,
                        'price' => isset($attrs['price']) ? (float) $attrs['price'] : null,
                        'message' => $r->message,
                        'status' => $attrs['status'] ?? null,
                        'car_name' => $r->relationLoaded('car') ? ($r->car?->name) : null,
                    ];
                }
            }

            return $data;
        });

        return $this->success($payload->values()->all(), 'OK', 200);
    }

    public function getConversationDetail(Request $request)
    {
        $senderId = (int) $request->input('sender_id');
        $receiverId = (int) $request->input('receiver_id');
        $requestedShipmentId = (int) ($request->input('shipment_id') ?? $request->query('shipment_id') ?? 0);

        if ($senderId === $receiverId) {
            return $this->success(['messages' => []], 'OK', 200);
        }

        $messagesQuery = Message::where(function ($q) use ($senderId, $receiverId) {
            $q->where('sender_id', $senderId)->where('receiver_id', $receiverId)
                ->orWhere('sender_id', $receiverId)->where('receiver_id', $senderId);
        })
            ->with('shipment')
            ->where('status', 'accepted')
            ->orderBy('created_at', 'asc');

        /** İlan / iş bazlı ayrı sohbet: shipment_id ile filtrele */
        if ($requestedShipmentId > 0) {
            $messagesQuery->where('shipment_id', $requestedShipmentId);
        } else {
            $messagesQuery->whereNull('shipment_id');
        }

        $messages = $messagesQuery->get();

        $messagesArray = $messages->toArray();
        $firstWithShipment = $messages->first(fn ($m) => $m->shipment_id !== null);
        $conversationShipment = $firstWithShipment?->shipment;
        $conversationShipmentId = $firstWithShipment?->shipment_id;

        $pair = [$senderId, $receiverId];

        if ($requestedShipmentId > 0) {
            $ship = Shipment::query()->find($requestedShipmentId);
            if ($ship && in_array((int) $ship->creater_id, $pair, true)) {
                $conversationShipment = $ship;
                $conversationShipmentId = $ship->id;
            }
        }

        // Mesaj yoksa veya hiç mesajda shipment yoksa: bu iki kullanıcı arasında teklif (request) var mı kontrol et
        if (! $conversationShipment) {
            $teklifRequest = Requests::with(['shipment', 'car'])
                ->where(function ($q) use ($senderId, $receiverId, $requestedShipmentId) {
                    $q->where(function ($q2) use ($senderId, $receiverId) {
                        $q2->where('user_id', $senderId)
                            ->whereHas('shipment', fn ($s) => $s->where('creater_id', $receiverId));
                    })->orWhere(function ($q2) use ($senderId, $receiverId) {
                        $q2->where('user_id', $receiverId)
                            ->whereHas('shipment', fn ($s) => $s->where('creater_id', $senderId));
                    });
                    if ($requestedShipmentId > 0) {
                        $q->where('shipment_id', $requestedShipmentId);
                    }
                })
                ->orderBy('created_at', 'desc')
                ->first();

            if ($teklifRequest && $teklifRequest->shipment) {
                $conversationShipment = $teklifRequest->shipment;
                $conversationShipmentId = $teklifRequest->shipment_id;
            }
        }

        $conversationRequest = null;
        if ($conversationShipmentId !== null && $conversationShipment instanceof Shipment) {
            $createrId = (int) $conversationShipment->creater_id;
            $conversationRequest = Requests::with(['car'])
                ->where('shipment_id', $conversationShipmentId)
                ->whereIn('user_id', $pair)
                ->when($createrId > 0, fn ($q) => $q->where('user_id', '!=', $createrId))
                ->orderByDesc('id')
                ->first();
        }

        $content = [
            'messages' => $messagesArray,
        ];
        if ($conversationShipmentId !== null) {
            $content['conversation_shipment_id'] = $conversationShipmentId;
            $content['conversation_shipment'] = $conversationShipment instanceof \Illuminate\Database\Eloquent\Model
                ? $conversationShipment->toArray()
                : $conversationShipment;
        }
        if ($conversationRequest) {
            $attrs = $conversationRequest->getAttributes();
            $content['conversation_request'] = [
                'id' => $conversationRequest->id,
                'price' => isset($attrs['price']) ? (float) $attrs['price'] : null,
                'message' => $conversationRequest->message,
                'status' => $attrs['status'] ?? null,
                'car_name' => $conversationRequest->relationLoaded('car') ? ($conversationRequest->car?->name) : null,
            ];
        }

        return $this->success($content, 'OK', 200);
    }

    /**
     * Verilen message id'lerinin hepsini is_read = 1 yapar (okundu işaretler).
     */
    public function markAsRead(Request $request)
    {
        $v = Validator::make($request->all(), [
            'message_ids' => 'required|array',
            'message_ids.*' => 'integer|exists:message,id',
        ]);

        if ($v->fails()) {
            return $this->error([], 'Validation hatası', $v->errors(), 422);
        }

        $ids = array_map('intval', $request->input('message_ids'));
        if (empty($ids)) {
            return $this->success(['updated' => 0], 'OK', 200);
        }

        $user = $request->user();
        /** Yalnızca giriş yapan kullanıcıya gelen mesajlar okunmuş sayılır + Pusher ile rozet */
        $updated = Message::whereIn('id', $ids)
            ->where('receiver_id', $user->id)
            ->update(['is_read' => true]);

        $count = (int) Message::query()
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->where('status', 'accepted')
            ->count();

        broadcast(new InboxUnreadUpdated((int) $user->id, $count));

        return $this->success(['updated' => $updated], 'OK', 200);
    }

    /**
     * Token ile gelen kullanıcının okunmamış mesajı var mı?
     * true: en az bir okunmamış mesaj var, false: yok.
     */
    public function hasUnread(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->success(['has_unread' => false], 'OK', 200);
        }

        $hasUnread = Message::where('receiver_id', $user->id)
            ->where('is_read', false)
            ->where('status', 'accepted')
            ->exists();

        return $this->success(['has_unread' => $hasUnread], 'OK', 200);
    }

    /**
     * Token ile gelen kullanıcının okunmamış mesaj sayısı (accepted, receiver, is_read false).
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->success(['unread_count' => 0], 'OK', 200);
        }

        $count = (int) Message::query()
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->where('status', 'accepted')
            ->count();

        return $this->success(['unread_count' => $count], 'OK', 200);
    }
}
