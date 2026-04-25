<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\PromoService;
use App\Http\Resources\V1\Public\PromoResource;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    protected PromoService $promoService;

    public function __construct(PromoService $promoService)
    {
        $this->promoService = $promoService;
    }

    /**
     * GET ALL ACTIVE PROMO CODES (PUBLIC)
     */
    public function index()
    {
        try {
            $promos = $this->promoService->getActivePromos();

            // Gunakan Resource agar formatting konsisten di semua API
            return $this->success(
                PromoResource::collection($promos),
                'Active promo codes retrieved'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve promo codes', $e->getMessage(), 500);
        }
    }

    /**
     * VALIDATE PROMO CODE
     */
    public function validate(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required|string|max:50',
            ]);

            $promo = $this->promoService->validatePromoCode($request->code);

            if (!$promo) {
                return $this->error('Invalid or expired promo code', null, 404);
            }

            $details = $this->promoService->getPromoDetails($request->code);

            return $this->success(new PromoResource($details), 'Promo code is valid');
        } catch (\Exception $e) {
            return $this->error('Validation failed', $e->getMessage(), 400);
        }
    }

    /**
     * GET PROMO DETAILS
     */
    public function show(string $code)
    {
        try {
            $details = $this->promoService->getPromoDetails($code);

            if (!$details) {
                return $this->notFound('Promo code not found');
            }

            return $this->success($details, 'Promo details retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve promo details', $e->getMessage(), 500);
        }
    }

    /**
     * GET EXPIRING PROMOS (INFORMATIONAL)
     */
    public function expiringPromos()
    {
        try {
            $promos = $this->promoService->getExpiringPromos();

            return $this->success([
                'data' => $promos->map(fn($promo) => $this->formatPromoResponse($promo)),
                'count' => $promos->count(),
            ], 'Expiring promos retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve expiring promos', $e->getMessage(), 500);
        }
    }

    /**
     * Helper: Format promo response
     */
    protected function formatPromoResponse($promo): array
    {
        return [
            'id' => $promo->id,
            'code' => $promo->promo_code,
            'description' => $promo->description,
            'discount_type' => $promo->discount_type,
            'discount_value' => $promo->discount_value,
            'max_discount' => $promo->max_discount,
            'remaining_uses' => max(0, $promo->usage_limit - $promo->used_count),
            'expires_at' => $promo->end_date,
        ];
    }
}
