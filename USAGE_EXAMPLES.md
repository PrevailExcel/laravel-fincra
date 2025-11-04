# Laravel Fincra - Complete Usage Examples

This document provides comprehensive examples of using the Laravel Fincra package.

## Table of Contents

1. [Installation & Setup](#installation--setup)
2. [Checkout Examples](#checkout-examples)
3. [Virtual Accounts](#virtual-accounts)
4. [Payouts](#payouts)
5. [Webhooks](#webhooks)
6. [Advanced Usage](#advanced-usage)

---

## Installation & Setup

### Step 1: Install Package

```bash
composer require prevailexcel/laravel-fincra
```

### Step 2: Publish Config

```bash
php artisan vendor:publish --provider="PrevailExcel\Fincra\FincraServiceProvider"
```

### Step 3: Configure .env

```env
FINCRA_PUBLIC_KEY=pk_test_your_public_key
FINCRA_SECRET_KEY=sk_test_your_secret_key
FINCRA_BUSINESS_ID=your_business_id
FINCRA_WEBHOOK_SECRET=your_webhook_secret
FINCRA_ENV=sandbox
```

---

## Checkout Examples

### Example 1: Simple Inline Checkout

#### Controller (PaymentController.php)

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PrevailExcel\Fincra\Facades\Fincra;

class PaymentController extends Controller
{
    public function showPaymentForm()
    {
        return view('payment.form');
    }

    public function processPayment(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
            'amount' => 'required|numeric|min:100',
        ]);

        try {
            // This will display the Fincra inline widget
            return Fincra::payWithWidget();
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function handleCallback(Request $request)
    {
        try {
            $paymentDetails = Fincra::getPaymentData();

            // Check payment status
            if ($paymentDetails['data']['status'] === 'success') {
                // Payment successful
                // Update order, send email, etc.
                
                return redirect()->route('payment.success')
                    ->with('success', 'Payment completed successfully!')
                    ->with('details', $paymentDetails['data']);
            }

            return redirect()->route('payment.failed')
                ->with('error', 'Payment was not completed');

        } catch (\Exception $e) {
            return redirect()->route('payment.failed')
                ->with('error', $e->getMessage());
        }
    }
}
```

#### Routes (web.php)

```php
use App\Http\Controllers\PaymentController;

Route::get('/payment', [PaymentController::class, 'showPaymentForm'])->name('payment.form');
Route::post('/payment/process', [PaymentController::class, 'processPayment'])->name('payment.process');
Route::callback(PaymentController::class, 'handleCallback');
Route::get('/payment/success', fn() => view('payment.success'))->name('payment.success');
Route::get('/payment/failed', fn() => view('payment.failed'))->name('payment.failed');
```

#### View (resources/views/payment/form.blade.php)

```blade
<!DOCTYPE html>
<html>
<head>
    <title>Make Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/[email protected]/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Make Payment</h3>
                    </div>
                    <div class="card-body">
                        @if(session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif

                        <form method="POST" action="{{ route('payment.process') }}">
                            @csrf
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phoneNumber" required>
                            </div>

                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount (NGN)</label>
                                <input type="number" class="form-control" id="amount" name="amount" min="100" required>
                            </div>

                            <input type="hidden" name="currency" value="NGN">
                            
                            <button type="submit" class="btn btn-primary w-100">Pay Now</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
```

---

### Example 2: Checkout Redirect with Custom Data

```php
public function processPaymentRedirect(Request $request)
{
    $orderId = $request->order_id;
    $order = Order::findOrFail($orderId);

    $data = [
        'amount' => $order->total_amount,
        'currency' => 'NGN',
        'customer' => [
            'name' => $order->customer_name,
            'email' => $order->customer_email,
            'phoneNumber' => $order->customer_phone,
        ],
        'reference' => 'ORDER_' . $order->id . '_' . time(),
        'paymentMethods' => ['card', 'bank_transfer'],
        'feeBearer' => 'customer',
        'redirectUrl' => route('payment.callback'),
        'metadata' => [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'invoice_number' => $order->invoice_number,
        ],
    ];

    return Fincra::checkoutRedirect($data)->redirectNow();
}
```

---

### Example 3: API Usage (Return Link Only)

```php
public function generatePaymentLink(Request $request)
{
    $data = [
        'amount' => $request->amount,
        'currency' => $request->currency ?? 'NGN',
        'customer' => [
            'name' => $request->name,
            'email' => $request->email,
            'phoneNumber' => $request->phone,
        ],
        'reference' => 'API_' . uniqid(),
        'redirectUrl' => $request->callback_url,
    ];

    $response = Fincra::checkoutRedirect($data, true);

    return response()->json([
        'success' => true,
        'payment_link' => $response['data']['link'],
        'reference' => $response['data']['reference'] ?? $response['data']['payCode'],
    ]);
}
```

---

## Virtual Accounts

### Example 4: Create Temporary Virtual Account

```php
public function createVirtualAccount(Request $request)
{
    try {
        $data = [
            'currency' => 'NGN',
            'accountType' => 'individual',
            'accountName' => $request->name,
            'bvn' => $request->bvn, // Required for Nigerian accounts
            'meansOfId' => [
                [
                    'type' => 'identityCard',
                    'number' => $request->id_number,
                ]
            ],
        ];

        $account = Fincra::createVirtualAccount($data);

        // Save to database
        VirtualAccount::create([
            'user_id' => auth()->id(),
            'account_id' => $account['data']['id'],
            'account_number' => $account['data']['accountNumber'],
            'account_name' => $account['data']['accountName'],
            'bank_name' => $account['data']['bankName'],
            'currency' => $account['data']['currency'],
        ]);

        return response()->json([
            'success' => true,
            'account' => $account['data'],
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 400);
    }
}
```

---

### Example 5: Create Permanent Virtual Account

```php
public function createPermanentAccount(Request $request)
{
    $data = [
        'currency' => 'NGN',
        'accountType' => 'individual',
        'accountName' => auth()->user()->name,
        'bvn' => auth()->user()->bvn,
        'isPermanent' => true, // This makes it permanent
        'meansOfId' => [
            [
                'type' => 'bvn',
                'number' => auth()->user()->bvn,
            ]
        ],
    ];

    $account = Fincra::createVirtualAccount($data);

    return response()->json($account);
}
```

---

### Example 6: List User's Virtual Accounts

```php
public function listVirtualAccounts()
{
    try {
        $accounts = Fincra::getVirtualAccounts();

        return view('accounts.list', [
            'accounts' => $accounts['data'],
        ]);
    } catch (\Exception $e) {
        return back()->with('error', $e->getMessage());
    }
}
```

---

## Payouts

### Example 7: Process Single Payout

```php
public function processPayout(Request $request)
{
    $request->validate([
        'amount' => 'required|numeric|min:100',
        'account_number' => 'required|string',
        'bank_code' => 'required|string',
        'account_name' => 'required|string',
    ]);

    try {
        $data = [
            'sourceCurrency' => 'NGN',
            'destinationCurrency' => 'NGN',
            'amount' => $request->amount,
            'business' => config('fincra.businessId'),
            'description' => $request->description ?? 'Payout',
            'beneficiary' => [
                'firstName' => $request->first_name,
                'lastName' => $request->last_name,
                'email' => $request->email,
                'phoneNumber' => $request->phone,
                'accountHolderName' => $request->account_name,
                'accountNumber' => $request->account_number,
                'type' => 'individual',
                'bankCode' => $request->bank_code,
                'country' => 'NG',
            ],
            'paymentDestination' => 'bank_account',
        ];

        $payout = Fincra::initiatePayout($data);

        // Log the payout
        PayoutLog::create([
            'user_id' => auth()->id(),
            'payout_id' => $payout['data']['id'],
            'reference' => $payout['data']['reference'],
            'amount' => $request->amount,
            'status' => $payout['data']['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payout initiated successfully',
            'payout' => $payout['data'],
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 400);
    }
}
```

---

### Example 8: Bulk Payouts

```php
public function bulkPayouts(Request $request)
{
    $payouts = $request->payouts; // Array of payout data
    $results = [];
    $errors = [];

    foreach ($payouts as $payout) {
        try {
            $data = [
                'sourceCurrency' => 'NGN',
                'destinationCurrency' => 'NGN',
                'amount' => $payout['amount'],
                'business' => config('fincra.businessId'),
                'description' => $payout['description'],
                'beneficiary' => [
                    'firstName' => $payout['first_name'],
                    'lastName' => $payout['last_name'],
                    'email' => $payout['email'],
                    'phoneNumber' => $payout['phone'],
                    'accountHolderName' => $payout['account_name'],
                    'accountNumber' => $payout['account_number'],
                    'type' => 'individual',
                    'bankCode' => $payout['bank_code'],
                    'country' => 'NG',
                ],
                'paymentDestination' => 'bank_account',
            ];

            $result = Fincra::initiatePayout($data);
            $results[] = $result['data'];

        } catch (\Exception $e) {
            $errors[] = [
                'recipient' => $payout['email'],
                'error' => $e->getMessage(),
            ];
        }
    }

    return response()->json([
        'success' => count($results),
        'failed' => count($errors),
        'results' => $results,
        'errors' => $errors,
    ]);
}
```

---

### Example 9: Using Saved Beneficiaries

```php
public function payoutToBeneficiary(Request $request)
{
    $beneficiaryId = $request->beneficiary_id;
    $amount = $request->amount;

    // Get beneficiary details
    $beneficiary = Fincra::getBeneficiary($beneficiaryId);

    $data = [
        'sourceCurrency' => 'NGN',
        'destinationCurrency' => 'NGN',
        'amount' => $amount,
        'business' => config('fincra.businessId'),
        'description' => $request->description,
        'beneficiary' => $beneficiary['data'],
        'paymentDestination' => 'bank_account',
    ];

    $payout = Fincra::initiatePayout($data);

    return response()->json($payout);
}
```

---

## Webhooks

### Example 10: Complete Webhook Handler

```php
<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessFincraPayment;
use App\Jobs\ProcessFincraP ayout;
use App\Models\Transaction;
use Illuminate\Http\Request;

class FincraWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        fincra()->getWebhookData()->processData(function ($data) {
            // Log webhook
            logger()->info('Fincra Webhook Received', $data);

            $event = $data['event'] ?? null;

            switch ($event) {
                case 'charge.successful':
                    $this->handleSuccessfulCharge($data);
                    break;

                case 'charge.failed':
                    $this->handleFailedCharge($data);
                    break;

                case 'payout.successful':
                    $this->handleSuccessfulPayout($data);
                    break;

                case 'payout.failed':
                    $this->handleFailedPayout($data);
                    break;

                case 'virtual_account.credited':
                    $this->handleVirtualAccountCredit($data);
                    break;

                default:
                    logger()->warning('Unknown webhook event: ' . $event);
            }
        });

        return response()->json(['status' => 'success'], 200);
    }

    protected function handleSuccessfulCharge($data)
    {
        $paymentData = $data['data'];
        $reference = $paymentData['merchantReference'] ?? $paymentData['reference'];

        // Find transaction
        $transaction = Transaction::where('reference', $reference)->first();

        if ($transaction) {
            $transaction->update([
                'status' => 'completed',
                'completed_at' => now(),
                'fincra_reference' => $paymentData['reference'],
            ]);

            // Dispatch job for heavy operations
            ProcessFincraPayment::dispatch($transaction, $paymentData);
        }
    }

    protected function handleFailedCharge($data)
    {
        $paymentData = $data['data'];
        $reference = $paymentData['merchantReference'] ?? $paymentData['reference'];

        $transaction = Transaction::where('reference', $reference)->first();

        if ($transaction) {
            $transaction->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => $paymentData['message'] ?? 'Payment failed',
            ]);
        }
    }

    protected function handleSuccessfulPayout($data)
    {
        $payoutData = $data['data'];
        
        // Update payout status in database
        // Send notification to user
        // etc.
    }

    protected function handleFailedPayout($data)
    {
        $payoutData = $data['data'];
        
        // Update payout status
        // Notify admin
        // etc.
    }

    protected function handleVirtualAccountCredit($data)
    {
        $accountData = $data['data'];
        
        // Credit user wallet
        // Send notification
        // etc.
    }
}
```

#### Don't forget to register the webhook route!

```php
// In routes/web.php
Route::webhook(FincraWebhookController::class, 'handleWebhook');
```

#### And add to CSRF exceptions:

```php
// In app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'fincra/webhook',
];
```

---

## Advanced Usage

### Example 11: Identity Verification

```php
public function verifyCustomer(Request $request)
{
    try {
        // Verify BVN
        $bvnVerification = Fincra::verifyBvn($request->bvn);

        // Verify Bank Account
        $accountVerification = Fincra::verifyBankAccount([
            'accountNumber' => $request->account_number,
            'bankCode' => $request->bank_code,
        ]);

        return response()->json([
            'bvn_valid' => $bvnVerification['status'],
            'account_valid' => $accountVerification['status'],
            'account_name' => $accountVerification['data']['accountName'] ?? null,
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 400);
    }
}
```

---

### Example 12: Currency Conversion

```php
public function convertCurrency(Request $request)
{
    $data = [
        'sourceCurrency' => $request->from_currency,
        'destinationCurrency' => $request->to_currency,
        'amount' => $request->amount,
    ];

    $conversion = Fincra::createConversion($data);

    return response()->json([
        'converted_amount' => $conversion['data']['convertedAmount'],
        'rate' => $conversion['data']['rate'],
        'fee' => $conversion['data']['fee'],
    ]);
}
```

---

### Example 13: Get Available Banks

```php
public function getBanks()
{
    $banks = Fincra::getBanks('NG'); // Nigeria

    return response()->json([
        'banks' => $banks['data'],
    ]);
}
```

---

### Example 14: Check Balance

```php
public function checkBalance()
{
    $balance = Fincra::getBalance();

    // Get specific currency balance
    $ngnBalance = Fincra::getBalance('NGN');

    return response()->json([
        'all_balances' => $balance['data'],
        'ngn_balance' => $ngnBalance['data'],
    ]);
}
```

---

## Testing

### Example 15: Feature Test

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_initiate_payment()
    {
        $response = $this->post(route('payment.process'), [
            'name' => 'John Doe',
            'email' => '[email protected]',
            'phoneNumber' => '+2348012345678',
            'amount' => 5000,
            'currency' => 'NGN',
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function payment_requires_valid_email()
    {
        $response = $this->post(route('payment.process'), [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'phoneNumber' => '+2348012345678',
            'amount' => 5000,
        ]);

        $response->assertSessionHasErrors('email');
    }
}
```

---

## Tips and Best Practices

1. **Always validate input** before sending to Fincra API
2. **Use queued jobs** for webhook processing
3. **Log all transactions** for audit trail
4. **Test in sandbox** before going live
5. **Handle exceptions** gracefully
6. **Verify webhooks** using signatures
7. **Store references** in your database
8. **Use environment variables** for sensitive data
9. **Implement retry logic** for failed operations
10. **Monitor webhook deliveries** and set up alerts

---

For more information, visit:
- [Fincra Documentation](https://docs.fincra.com)
- [Package Repository](https://github.com/prevailexcel/laravel-fincra)