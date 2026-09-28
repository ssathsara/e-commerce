<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponRequest;
use App\Http\Resources\Admin\CouponResource;
use App\Http\Resources\Admin\CouponUsageResource;
use App\Models\Coupon;
use App\Support\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Coupon CRUD. Coupon validation rules at purchase time live in CouponService.
 */
class CouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive,expired'],
        ]);

        $search = $request->query('search');

        $coupons = Coupon::query()
            ->withCount('usages')
            ->when($search, fn ($q) => $q->where('code', 'like', '%'.addcslashes(strtoupper($search), '%_\\').'%'))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true)->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now())))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->query('status') === 'expired', fn ($q) => $q->where('expires_at', '<=', now()))
            ->latest('id')
            ->paginate($this->perPage());

        return $this->paginated($coupons, CouponResource::class);
    }

    public function store(CouponRequest $request): JsonResponse
    {
        $coupon = Coupon::create($request->toModelAttributes());

        AuditLog::info('coupon.created', ['coupon_id' => $coupon->id, 'code' => $coupon->code]);

        return $this->created(new CouponResource($coupon->loadCount('usages')), 'Coupon created successfully.');
    }

    public function show(Coupon $coupon): JsonResponse
    {
        return $this->success(new CouponResource($coupon->loadCount('usages')));
    }

    public function usages(Coupon $coupon): JsonResponse
    {
        $usages = $coupon->usages()
            ->with(['user:id,name,email', 'order:id,order_number'])
            ->latest('id')
            ->paginate($this->perPage());

        return $this->paginated($usages, CouponUsageResource::class);
    }

    public function update(CouponRequest $request, Coupon $coupon): JsonResponse
    {
        $coupon->update($request->toModelAttributes());

        AuditLog::info('coupon.updated', ['coupon_id' => $coupon->id]);

        return $this->success(new CouponResource($coupon->loadCount('usages')), 'Coupon updated successfully.');
    }

    public function toggle(Coupon $coupon): JsonResponse
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        AuditLog::info('coupon.toggled', ['coupon_id' => $coupon->id, 'is_active' => $coupon->is_active]);

        return $this->success(new CouponResource($coupon->loadCount('usages')), $coupon->is_active ? 'Coupon activated.' : 'Coupon deactivated.');
    }

    /** Soft delete: usage history on past orders is preserved. */
    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        AuditLog::info('coupon.deleted', ['coupon_id' => $coupon->id]);

        return $this->message('Coupon deleted successfully.');
    }
}
