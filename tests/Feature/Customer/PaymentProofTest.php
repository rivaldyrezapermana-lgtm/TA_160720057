<?php

namespace Tests\Feature\Customer;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentProofTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin'.uniqid().'@labasa.test',
            'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function customer(): User
    {
        return User::create([
            'name' => 'Pembeli', 'email' => 'beli'.uniqid().'@labasa.test',
            'password' => 'password', 'role' => User::ROLE_PEMBELI,
        ]);
    }

    private function product(int $stock = 10): Product
    {
        $category = Category::firstOrCreate(['slug' => 'gamis'], ['name' => 'Gamis']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Gamis Uji', 'sku' => 'SKU-'.uniqid(),
            'price' => 100000, 'stock' => $stock, 'is_active' => true,
        ]);
    }

    /** Order owned by $customer with one item and a pending payment. */
    private function orderWithPayment(User $customer, Product $product, array $paymentAttrs = []): Order
    {
        $order = Order::create([
            'user_id' => $customer->id, 'code' => 'ORD-TEST-'.uniqid(),
            'total' => 125000, 'status' => 'pending', 'shipping_address' => 'Jl. Uji',
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'size' => 'M',
            'qty' => 2, 'price' => 100000, 'subtotal' => 200000,
        ]);
        Payment::create(array_merge([
            'order_id' => $order->id, 'method' => 'transfer',
            'amount' => 125000, 'status' => 'pending', 'proof_image' => 'proofs/old.jpg',
        ], $paymentAttrs));

        return $order->fresh();
    }

    public function test_admin_can_reject_a_payment_with_a_reason(): void
    {
        $customer = $this->customer();
        $order = $this->orderWithPayment($customer, $this->product());

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.reject', $order), ['note' => 'Bukti tidak terbaca'])
            ->assertRedirect();

        $payment = $order->payment->fresh();
        $this->assertSame('rejected', $payment->status);
        $this->assertSame('Bukti tidak terbaca', $payment->note);
        $this->assertNull($payment->paid_at);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_admin_cannot_verify_a_payment_without_proof(): void
    {
        $customer = $this->customer();
        $order = $this->orderWithPayment($customer, $this->product(), ['proof_image' => null]);

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.verify', $order))
            ->assertRedirect();

        $this->assertSame('pending', $order->payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_customer_can_reupload_proof_after_rejection(): void
    {
        Storage::fake('public');
        $customer = $this->customer();
        $order = $this->orderWithPayment($customer, $this->product(), [
            'status' => 'rejected', 'note' => 'Bukti tidak terbaca',
        ]);

        $this->actingAs($customer)
            ->post(route('checkout.proof', $order), [
                'proof' => UploadedFile::fake()->image('bukti-baru.jpg'),
            ])
            ->assertRedirect();

        $payment = $order->payment->fresh();
        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->note);
        Storage::disk('public')->assertExists($payment->proof_image);
    }

    public function test_customer_cannot_reupload_after_verified(): void
    {
        Storage::fake('public');
        $customer = $this->customer();
        $order = $this->orderWithPayment($customer, $this->product(), [
            'status' => 'verified', 'proof_image' => 'proofs/verified.jpg',
        ]);

        $this->actingAs($customer)
            ->post(route('checkout.proof', $order), [
                'proof' => UploadedFile::fake()->image('lain.jpg'),
            ])
            ->assertRedirect();

        $payment = $order->payment->fresh();
        $this->assertSame('verified', $payment->status);
        $this->assertSame('proofs/verified.jpg', $payment->proof_image);
    }

    public function test_full_cycle_reject_then_reupload_then_verify(): void
    {
        Storage::fake('public');
        $customer = $this->customer();
        $product = $this->product(10);
        $order = $this->orderWithPayment($customer, $product);

        // admin rejects
        $this->actingAs($this->admin())
            ->patch(route('admin.orders.reject', $order), ['note' => 'Salah nominal']);
        $this->assertSame('rejected', $order->payment->fresh()->status);

        // customer re-uploads
        $this->actingAs($customer)
            ->post(route('checkout.proof', $order), [
                'proof' => UploadedFile::fake()->image('ulang.jpg'),
            ]);
        $this->assertSame('pending', $order->payment->fresh()->status);

        // admin verifies
        $this->actingAs($this->admin())
            ->patch(route('admin.orders.verify', $order));

        $this->assertSame('verified', $order->payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock); // 10 - 2
    }

    public function test_rejected_order_page_shows_reason_and_reupload_form(): void
    {
        $customer = $this->customer();
        $order = $this->orderWithPayment($customer, $this->product(), [
            'status' => 'rejected', 'note' => 'Nominal transfer kurang',
        ]);

        $this->actingAs($customer)
            ->get(route('customer.orders.show', $order))
            ->assertOk()
            ->assertSee('Nominal transfer kurang')
            ->assertSee('name="proof"', false);
    }

    public function test_verified_order_page_hides_upload_form(): void
    {
        $customer = $this->customer();
        $order = $this->orderWithPayment($customer, $this->product(), [
            'status' => 'verified', 'proof_image' => 'proofs/ok.jpg',
        ]);

        $this->actingAs($customer)
            ->get(route('customer.orders.show', $order))
            ->assertOk()
            ->assertDontSee('name="proof"', false);
    }

    public function test_checkout_rejects_non_transfer_payment_method(): void
    {
        $customer = $this->customer();
        $product = $this->product();

        // seed a cart item via session-backed cart
        $this->actingAs($customer)
            ->post(route('cart.add'), ['product_id' => $product->id, 'size' => 'M', 'qty' => 1]);

        $this->actingAs($customer)
            ->post(route('checkout.store'), [
                'recipient' => 'Beli', 'phone' => '0812', 'shipping_address' => 'Jl. Uji',
                'payment_method' => 'ewallet',
            ])
            ->assertSessionHasErrors('payment_method');
    }

    public function test_checkout_page_offers_only_bca_transfer(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $this->actingAs($customer)
            ->post(route('cart.add'), ['product_id' => $product->id, 'size' => 'M', 'qty' => 1]);

        $this->actingAs($customer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Transfer Bank BCA')
            ->assertSee('BCA 1234567890 a/n Toko Labasa')
            ->assertDontSee('E-Wallet');
    }

    public function test_admin_order_page_shows_reject_form_and_reason(): void
    {
        $customer = $this->customer();
        $order = $this->orderWithPayment($customer, $this->product(), [
            'status' => 'rejected', 'note' => 'Bukti buram',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Tolak Pembayaran')
            ->assertSee('Bukti buram');
    }
}
