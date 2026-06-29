# Payment / Proof-of-Payment Loop — Design

Date: 2026-06-29
Status: Approved

## Context

The Labasa storefront already wires most of the payment happy path with real
Eloquent (not stubs):

- `CheckoutController::store` creates an `Order` + a `pending` `Payment`.
- `CheckoutController::success` shows BCA transfer instructions + an upload form.
- `CheckoutController::uploadProof` stores the proof image to `storage/app/public/proofs`.
- `customer/orders/show` shows payment status + proof.
- `admin/orders/show` shows the proof + a "Verifikasi Pembayaran" button.
- `Admin\OrderController::verifyPayment` flips payment→`verified`, order→`paid`,
  and decrements product stock inside a transaction.

The `payments` table and the `status-badge` component already support a
`rejected` status, but nothing ever sets it.

## Gaps this design closes

1. **No reject flow.** Admin can only verify; a fake/blurry proof can't be bounced.
2. **Re-upload deadlock.** The customer upload form only renders when *no* proof
   exists, so a rejected customer can never re-submit.
3. **Payment method.** The business only accepts **BCA transfer** — E-Wallet (and
   COD) must be removed from the checkout flow.

## Changes

### 1. Schema — rejection reason

- New migration `add_note_to_payments_table`: nullable `string note` after `status`.
- Add `note` to `Payment::$fillable`.

### 2. Admin — reject action (`Admin\OrderController`)

- `rejectPayment(Request, Order)`: validates optional `note` (`max:500`), sets
  `payment.status = 'rejected'`, stores `note`, clears `paid_at`. Order stays
  `pending` (it was never moved to `paid`; no stock was decremented, so nothing
  to roll back).
- Route: `PATCH admin/orders/{order}/reject-payment` → `admin.orders.reject`.
- `verifyPayment` guard: refuse to verify unless a `proof_image` exists.
- `admin/orders/show` view: show **Verifikasi** + **Tolak** (reject reveals a
  reason textarea) while status is `pending` AND a proof exists; show the stored
  rejection note when status is `rejected`.

### 3. Customer — fix the re-upload deadlock (`customer/orders/show`)

- Form condition becomes "payment not yet verified":
  - pending, no proof → upload form
  - pending, with proof → "Menunggu verifikasi" + view link
  - rejected → red rejection reason + re-upload form
  - verified → view link only, no form
- `CheckoutController::uploadProof` guard: refuse re-upload once `verified`;
  re-upload after `rejected` resets status to `pending` and clears `note`.

### 4. BCA transfer only

- `checkout.blade.php`: remove the E-Wallet radio; keep only **Transfer Bank (BCA)**.
- `CheckoutController::store` validation: `payment_method` → `['required', 'in:transfer']`.
- Success page already shows only `BANK_INFO` (BCA); no change needed.

## Out of scope

E-Wallet, COD, payment gateways, email/notifications.

## Testing

Feature test covering:
- reject → re-upload → verify cycle
- "can't verify without proof" guard
- "can't re-upload after verified" guard

Uses `Storage::fake('public')`.
