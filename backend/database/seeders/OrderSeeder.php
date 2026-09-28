<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\RoleName;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Events\PaymentSucceeded;
use App\Exceptions\BusinessRuleException;
use App\Models\Coupon;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutService;
use App\Services\Checkout\PlaceOrderData;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Places realistic orders through the real checkout and order services, then
 * back-dates them across the last ~60 days so dashboards and reports have history.
 * Notification events are faked so seeding does not queue hundreds of emails.
 */
class OrderSeeder extends Seeder
{
    public function run(CartService $carts, CheckoutService $checkout, OrderService $orders, PaymentService $payments): void
    {
        Event::fakeFor(function () use ($carts, $checkout, $orders, $payments) {
            $admin = User::role(RoleName::SuperAdmin->value)->firstOrFail();
            $customers = User::role(RoleName::Customer->value)->with('addresses')->get()->filter(fn ($u) => $u->addresses->isNotEmpty());
            $couponCodes = Coupon::query()->where('is_active', true)->pluck('code')->all();

            for ($i = 0; $i < 60; $i++) {
                $customer = $i < 4 ? $customers->firstWhere('email', 'customer@example.com') : $customers->random();
                $order = $this->placeRandomOrder($customer, $carts, $checkout, $couponCodes, $i);

                if ($order === null) {
                    continue;
                }

                $this->progress($order, $orders, $payments, $admin);
                $this->backdate($order, now()->subDays(random_int(0, 60))->subMinutes(random_int(0, 1440)));
            }
        }, [OrderPlaced::class, OrderStatusChanged::class, PaymentSucceeded::class]);
    }

    private function placeRandomOrder(User $customer, CartService $carts, CheckoutService $checkout, array $couponCodes, int $i): ?Order
    {
        $cart = $carts->resolve($customer, null, create: true);
        $carts->clear($cart);

        $products = Product::query()
            ->where('status', ProductStatus::Active)
            ->where('stock_quantity', '>', 3)
            ->with('activeVariants')
            ->inRandomOrder()
            ->limit(random_int(1, 3))
            ->get();

        foreach ($products as $product) {
            $variant = $product->activeVariants->where('stock_quantity', '>', 2)->shuffle()->first();

            if ($product->activeVariants->isNotEmpty() && $variant === null) {
                continue;
            }

            try {
                $carts->add($cart, $product->id, $variant?->id, random_int(1, 2));
            } catch (BusinessRuleException) {
                // Stock ran low during seeding; skip this product.
            }
        }

        if ($couponCodes !== [] && random_int(1, 5) === 1) {
            try {
                $carts->applyCoupon($cart, $couponCodes[array_rand($couponCodes)], $customer);
            } catch (BusinessRuleException) {
                // Coupon not applicable to this cart.
            }
        }

        $address = $customer->addresses->first();
        $method = random_int(1, 3) === 1 ? PaymentMethod::CashOnDelivery : PaymentMethod::Card;

        try {
            return $checkout->placeOrder($customer, new PlaceOrderData(
                idempotencyKey: (string) Str::uuid(),
                shippingAddressId: $address->id,
                billingAddressId: $address->id,
                shippingMethod: random_int(1, 4) === 1 ? 'express' : 'standard',
                paymentMethod: $method,
                // About 1 in 12 card payments is declined, to demo failed payments.
                paymentToken: $method === PaymentMethod::Card ? ($i % 12 === 5 ? 'tok_chargeDeclined' : 'tok_visa') : null,
                notes: random_int(1, 6) === 1 ? 'Please leave the parcel at the front door.' : null,
            ))->order;
        } catch (BusinessRuleException) {
            return null;
        }
    }

    /** Move the order along a realistic lifecycle. */
    private function progress(Order $order, OrderService $orders, PaymentService $payments, User $admin): void
    {
        $order->refresh();
        $roll = random_int(1, 100);

        if ($order->payment_status === PaymentStatus::Failed) {
            if ($roll <= 50) {
                $orders->transition($order, OrderStatus::Cancelled, $admin);
            }

            return;
        }

        if ($order->payment_method === PaymentMethod::CashOnDelivery && $roll > 15) {
            $orders->transition($order, OrderStatus::Confirmed, $admin);
        }

        if ($roll <= 8) {
            $orders->transition($order->refresh(), OrderStatus::Cancelled, $admin);

            return;
        }

        $path = match (true) {
            $roll <= 20 => [],
            $roll <= 35 => [OrderStatus::Processing],
            $roll <= 50 => [OrderStatus::Processing, OrderStatus::Shipped],
            default => [OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered],
        };

        foreach ($path as $status) {
            $order->refresh();
            if ($order->status->canTransitionTo($status)) {
                $orders->transition($order, $status, $admin, [
                    'tracking_number' => $status === OrderStatus::Shipped ? '1Z'.strtoupper(Str::random(14)) : null,
                ]);
            }
        }

        $order->refresh();
        if ($order->status === OrderStatus::Delivered && $order->payment_method === PaymentMethod::CashOnDelivery) {
            $payments->updateStatusManually($order, PaymentStatus::Paid);
        }
    }

    private function backdate(Order $order, \DateTimeInterface $at): void
    {
        DB::transaction(function () use ($order, $at) {
            $order->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
            $order->items()->update(['created_at' => $at, 'updated_at' => $at]);
            $order->payments()->update(['created_at' => $at, 'updated_at' => $at]);
            InventoryMovement::query()->where('order_id', $order->id)->update(['created_at' => $at, 'updated_at' => $at]);
            DB::table('coupon_usages')->where('order_id', $order->id)->update(['created_at' => $at, 'updated_at' => $at]);
        });
    }
}
