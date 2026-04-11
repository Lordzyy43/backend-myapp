<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\PromoService;
use App\Models\Promo;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PromoController extends Controller
{
    protected PromoService $promoService;

    public function __construct(PromoService $promoService)
    {
        $this->promoService = $promoService;
    }

    /**
     * LIST ALL PROMOS (ADMIN)
     */
    public function index()
    {
        try {
            $promos = Promo::with([])
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return $this->success([
                'data' => $promos->items(),
                'pagination' => [
                    'current_page' => $promos->currentPage(),
                    'last_page' => $promos->lastPage(),
                    'per_page' => $promos->perPage(),
                    'total' => $promos->total(),
                ],
            ], 'Promo codes retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve promo codes', $e->getMessage(), 500);
        }
    }

    /**
     * CREATE NEW PROMO
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'promo_code' => 'required|string|unique:promos,promo_code|max:50',
                'description' => 'nullable|string|max:255',
                'discount_type' => 'required|in:percentage,fixed',
                'discount_value' => 'required|numeric|min:0',
                'max_discount' => 'nullable|numeric|min:0',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'usage_limit' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
            ]);

            $promo = $this->promoService->createPromo($validated);

            return $this->created($promo, 'Promo code created successfully');
        } catch (ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Failed to create promo code', $e->getMessage(), 500);
        }
    }

    /**
     * SHOW PROMO DETAILS
     */
    public function show(string $id)
    {
        try {
            $promo = Promo::findOrFail($id);

            return $this->success([
                'promo' => $promo,
                'details' => [
                    'total_used' => $promo->used_count,
                    'remaining_uses' => $promo->usage_limit > 0
                        ? max(0, $promo->usage_limit - $promo->used_count)
                        : 'Unlimited',
                    'days_remaining' => now()->diffInDays($promo->end_date),
                    'is_expired' => $promo->end_date < now(),
                ],
            ], 'Promo details retrieved');
        } catch (\Exception $e) {
            return $this->notFound('Promo code not found');
        }
    }

    /**
     * UPDATE PROMO
     */
    public function update(Request $request, string $id)
    {
        try {
            $promo = Promo::findOrFail($id);

            $validated = $request->validate([
                'description' => 'nullable|string|max:255',
                'discount_type' => 'sometimes|in:percentage,fixed',
                'discount_value' => 'sometimes|numeric|min:0',
                'max_discount' => 'nullable|numeric|min:0',
                'end_date' => 'sometimes|date|after:start_date',
                'usage_limit' => 'nullable|integer|min:0',
                'is_active' => 'sometimes|boolean',
            ]);

            $updated = $this->promoService->updatePromo($promo, $validated);

            return $this->success($updated, 'Promo code updated successfully');
        } catch (ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->notFound('Promo code not found');
        }
    }

    /**
     * DELETE PROMO
     */
    public function destroy(string $id)
    {
        try {
            $promo = Promo::findOrFail($id);

            $promo->delete();

            return $this->success(null, 'Promo code deleted successfully');
        } catch (\Exception $e) {
            return $this->notFound('Promo code not found');
        }
    }

    /**
     * DEACTIVATE EXPIRED PROMOS
     */
    public function deactivateExpired()
    {
        try {
            $count = $this->promoService->deactivateExpiredPromos();

            return $this->success([
                'deactivated_count' => $count,
            ], "Deactivated {$count} expired promo codes");
        } catch (\Exception $e) {
            return $this->error('Failed to deactivate expired promos', $e->getMessage(), 500);
        }
    }

    /**
     * GET PROMO STATISTICS
     */
    public function statistics()
    {
        try {
            $total = Promo::count();
            $active = Promo::where('is_active', true)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->count();
            $totalUses = Promo::sum('used_count');
            $expiringSoon = $this->promoService->getExpiringPromos()->count();

            return $this->success([
                'total_promos' => $total,
                'active_promos' => $active,
                'total_uses' => $totalUses,
                'expiring_soon' => $expiringSoon,
            ], 'Promo statistics retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve statistics', $e->getMessage(), 500);
        }
    }
}
