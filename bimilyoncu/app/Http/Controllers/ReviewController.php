<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewImage;
use App\Models\ReviewReply;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Alıcı yorumları: yalnızca ürünü satın alan yorum yapabilir; yorum sahibi satıcıya tekrar tekrar yanıt verebilir.
 */
class ReviewController extends Controller
{
    /** Ürün sayfasında "Yorum Yap" butonu ve kendi yorumundaki "Yanıtla" için */
    public function status(Request $request, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->firstOrFail();
        $user = $request->user();
        $ownReviewId = $product->reviews()->where('user_id', $user->id)->value('id');
        $purchased = $this->hasPurchased($user, $product);

        return response()->json([
            'has_purchased' => $purchased,
            'can_review' => $purchased && ! $ownReviewId,
            'review_id' => $ownReviewId,
        ]);
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->firstOrFail();
        $user = $request->user();

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'min:10', 'max:2000'],
            'images' => ['nullable', 'array', 'max:'.ReviewImage::MAX_PER_REVIEW],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'rating.required' => 'Puan seçin.',
            'comment.required' => 'Yorumunuzu yazın.',
            'comment.min' => 'Yorum en az 10 karakter olmalıdır.',
            'images.max' => 'En fazla '.ReviewImage::MAX_PER_REVIEW.' fotoğraf ekleyebilirsin.',
            'images.*.image' => 'Yalnızca fotoğraf yükleyebilirsin.',
            'images.*.mimes' => 'JPG, PNG ya da WEBP yükleyebilirsin.',
            'images.*.max' => 'Her fotoğraf en fazla 5 MB olabilir.',
        ]);

        if (! $this->hasPurchased($user, $product)) {
            throw ValidationException::withMessages(['comment' => 'Yalnızca satın aldığın ürünlere yorum yapabilirsin.']);
        }
        if ($product->reviews()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['comment' => 'Bu ürüne zaten yorum yaptın.']);
        }

        $stored = [];
        try {
            $review = DB::transaction(function () use ($request, $product, $user, $data, &$stored) {
                $review = $product->reviews()->create([
                    'rating' => $data['rating'],
                    'comment' => $data['comment'],
                    'user_id' => $user->id,
                    'author_name' => $user->name,
                    'reviewed_at' => now()->toDateString(),
                ]);

                foreach ($request->file('images', []) as $index => $file) {
                    $path = $file->store("yorumlar/{$review->id}", 'public');
                    $stored[] = $path;
                    $review->images()->create(['path' => '/storage/'.$path, 'sort' => $index]);
                }

                return $review;
            });
        } catch (\Throwable $e) {
            // Kayıt geri alındıysa yüklenen fotoğraflar da kalmasın
            Storage::disk('public')->delete($stored);
            throw $e;
        }

        return response()->json(['review' => $review->load(['images', 'replies'])->toPublicArray($product->seller)], 201);
    }

    /** Yorum sahibi alıcı, satıcıyla yazışmaya devam eder */
    public function reply(Request $request, Review $review): JsonResponse
    {
        if ($review->user_id !== $request->user()->id) {
            abort(403, 'Yalnızca kendi yorumuna yanıt verebilirsin.');
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:1000'],
        ], [
            'body.required' => 'Yanıt boş olamaz.',
        ]);

        $review->replies()->create([
            'user_id' => $request->user()->id,
            'author_type' => ReviewReply::AUTHOR_CUSTOMER,
            'body' => $data['body'],
        ]);

        return response()->json(['review' => $review->load(['images', 'replies'])->toPublicArray($review->product->seller)]);
    }

    /** Yalnızca ödemesi alınmış siparişler sayılır (ileride iptal/başarısız ödeme olursa yorum hakkı vermez) */
    private function hasPurchased(User $user, Product $product): bool
    {
        return $user->orders()
            ->where('status', Order::STATUS_PAID)
            ->whereHas('items', fn ($query) => $query->where('product_id', $product->id))
            ->exists();
    }
}
