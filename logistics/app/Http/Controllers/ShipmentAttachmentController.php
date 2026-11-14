<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentAttachment;
use App\Models\ShipmentOrder;
use App\Support\PublicStorageUrl;
use App\Support\ShipmentAttachmentStorage;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ShipmentAttachmentController extends Controller
{
    use ResponseTrait;

    public function index(Request $request, int $shipmentId)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Unauthorized', [], 401);
        }

        $shipment = Shipment::find($shipmentId);
        if (! $shipment) {
            return $this->error([], 'İlan bulunamadı.', [], 404);
        }

        if (! $this->canManageAttachments($user->id, $shipment)) {
            return $this->error([], 'Bu ilan için belge görüntüleme yetkiniz yok.', [], 403);
        }

        $attachments = ShipmentAttachment::query()
            ->with('creator:id,first_name,last_name')
            ->where('shipment_id', $shipmentId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ShipmentAttachment $item) => $this->transformAttachment($request, $item));

        return $this->success([
            'attachments' => $attachments,
        ], 'Belgeler listelendi.');
    }

    public function store(Request $request, int $shipmentId)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Unauthorized', [], 401);
        }

        $shipment = Shipment::find($shipmentId);
        if (! $shipment) {
            return $this->error([], 'İlan bulunamadı.', [], 404);
        }

        if (! $this->canManageAttachments($user->id, $shipment)) {
            return $this->error([], 'Bu ilan için belge yükleme yetkiniz yok.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'image' => 'required|file|max:5120',
        ], [
            'title.required' => 'Başlık gerekli.',
            'image.required' => 'Belge seçin.',
            'image.max' => 'Belge en fazla 5MB olabilir.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Doğrulama hatası', $validator->errors()->toArray(), 422);
        }

        $file = $request->file('image');
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (! in_array($file->getMimeType(), $allowedMimes, true)) {
            return $this->error([], 'Geçersiz dosya türü. Sadece JPG, PNG, WEBP veya PDF yükleyebilirsiniz.', [], 422);
        }

        $ext = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
        $filename = Str::random(40).'.'.strtolower($ext);

        try {
            $bytes = $file->getContent();
        } catch (\Throwable) {
            return $this->error([], 'Dosya okunamadı.', [], 422);
        }

        if ($bytes === '') {
            return $this->error([], 'Dosya boş.', [], 422);
        }

        try {
            $path = ShipmentAttachmentStorage::put($shipmentId, $filename, $bytes);
        } catch (\Throwable $e) {
            report($e);

            return $this->error([], 'Belge kaydedilemedi.', [], 500);
        }

        $attachment = ShipmentAttachment::create([
            'shipment_id' => $shipmentId,
            'creator_id' => $user->id,
            'title' => trim($request->input('title')),
            'description' => $request->input('description') ? trim($request->input('description')) : null,
            'image' => $path,
        ]);
        $attachment->load('creator:id,first_name,last_name');

        return $this->success([
            'attachment' => $this->transformAttachment($request, $attachment),
        ], 'Belge yüklendi.', 201);
    }

    private function canManageAttachments(int $userId, Shipment $shipment): bool
    {
        if (! in_array($shipment->status, ['done', 'inactive'], true)) {
            return false;
        }

        if ((int) $shipment->creater_id === $userId) {
            return true;
        }

        return ShipmentOrder::query()
            ->where('shipment_id', $shipment->id)
            ->where(function ($q) use ($userId) {
                $q->where('buyer_user', $userId)
                    ->orWhere('creater_user', $userId);
            })
            ->exists();
    }

    private function transformAttachment(Request $request, ShipmentAttachment $attachment): array
    {
        $creator = $attachment->creator;
        $creatorName = $creator
            ? trim($creator->first_name.' '.$creator->last_name)
            : null;

        return [
            'id' => $attachment->id,
            'shipment_id' => $attachment->shipment_id,
            'creator_id' => $attachment->creator_id,
            'creator_name' => $creatorName !== '' ? $creatorName : null,
            'creator' => $creator ? [
                'id' => $creator->id,
                'full_name' => $creatorName !== '' ? $creatorName : $creator->full_name,
            ] : null,
            'title' => $attachment->title,
            'description' => $attachment->description,
            'image' => $attachment->image,
            'image_url' => PublicStorageUrl::absoluteForRequest($request, $attachment->image),
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }
}
