<?php

namespace PrevailExcel\Fincra;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use PrevailExcel\Fincra\Exceptions\IsNullException;
use PrevailExcel\Fincra\Traits\HandlesApiRequests;

class Fincra
{
    use HandlesApiRequests;

    /**
     * Base URL
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Secret Key
     *
     * @var string
     */
    protected $secretKey;

    /**
     * Public Key
     *
     * @var string
     */
    protected $publicKey;

    /**
     * Business ID
     *
     * @var string
     */
    protected $businessId;

    /**
     * Guzzle HTTP Client
     *
     * @var Client
     */
    protected $client;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setKey();
        $this->setBaseUrl();
        $this->setRequestOptions();
    }

    /**
     * Set API keys
     */
    public function setKey()
    {
        $this->secretKey = Config::get('fincra.secretKey');
        $this->publicKey = Config::get('fincra.publicKey');
        $this->businessId = Config::get('fincra.businessId');
    }

    /**
     * Set Base URL
     */
    public function setBaseUrl()
    {
        $env = Config::get('fincra.env');
        $this->baseUrl = $env === 'live'
            ? Config::get('fincra.liveUrl')
            : Config::get('fincra.sandboxUrl');
    }

    /**
     * Set HTTP Client options
     */
    private function setRequestOptions()
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'api-key' => $this->secretKey,
                'x-pub-key' => $this->publicKey,
                'x-business-id' => $this->businessId,
            ],
        ]);
    }

    /**
     * Display Inline Checkout Widget
     *
     * @param array|null $data
     * @return \Illuminate\View\View
     */
    public function payWithWidget(?array $data = null)
    {
        $data = $data ?? request()->all();
        
        $this->validateCheckoutData($data);

        return view('fincra::checkout', [
            'publicKey' => $this->publicKey,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'NGN',
            'customerName' => $data['name'] ?? $data['customerName'] ?? null,
            'customerEmail' => $data['email'] ?? $data['customerEmail'] ?? null,
            'customerPhone' => $data['phoneNumber'] ?? $data['phone'] ?? $data['customerPhone'] ?? null,
            'reference' => $data['reference'] ?? $this->generateReference(),
            'feeBearer' => $data['feeBearer'] ?? 'business',
            'metadata' => $data['metadata'] ?? [],
            'paymentMethods' => $data['paymentMethods'] ?? null,
            'callbackUrl' => $data['callbackUrl'] ?? route('fincra.callback'),
        ]);
    }

    /**
     * Create Checkout Payment (Redirect)
     *
     * @param array|null $data
     * @param bool $returnLink
     * @return \Illuminate\Http\RedirectResponse|array
     */
    public function checkoutRedirect(?array $data = null, bool $returnLink = false)
    {
        $data = $data ?? request()->all();
        
        $this->validateCheckoutData($data);

        $payload = [
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'NGN',
            'customer' => [
                'name' => $data['name'] ?? $data['customer']['name'] ?? null,
                'email' => $data['email'] ?? $data['customer']['email'] ?? null,
                'phoneNumber' => $data['phoneNumber'] ?? $data['phone'] ?? $data['customer']['phoneNumber'] ?? null,
            ],
            'feeBearer' => $data['feeBearer'] ?? 'business',
            'reference' => $data['reference'] ?? $this->generateReference(),
            'redirectUrl' => $data['redirectUrl'] ?? $data['callbackUrl'] ?? route('fincra.callback'),
        ];

        // Optional fields
        if (isset($data['paymentMethods'])) {
            $payload['paymentMethods'] = $data['paymentMethods'];
        }
        
        if (isset($data['defaultPaymentMethod'])) {
            $payload['defaultPaymentMethod'] = $data['defaultPaymentMethod'];
        }
        
        if (isset($data['metadata'])) {
            $payload['metadata'] = $data['metadata'];
        }
        
        if (isset($data['successMessage'])) {
            $payload['successMessage'] = $data['successMessage'];
        }
        
        if (isset($data['settlementDestination'])) {
            $payload['settlementDestination'] = $data['settlementDestination'];
        }

        $response = $this->makeRequest('POST', '/checkout/payments', $payload);

        if ($returnLink) {
            return $response;
        }

        if (isset($response['data']['link'])) {
            return redirect($response['data']['link']);
        }

        throw new \Exception('Unable to generate checkout link');
    }

    /**
     * Get Payment Data (Verify Transaction)
     *
     * @param string|null $reference
     * @return array
     */
    public function getPaymentData(?string $reference = null)
    {
        $reference = $reference ?? request()->reference;

        if (!$reference) {
            throw new IsNullException('Transaction reference is required');
        }

        return $this->verifyPayment($reference);
    }

    /**
     * Verify Payment
     *
     * @param string|null $reference
     * @return array
     */
    public function verifyPayment(?string $reference = null)
    {
        $reference = $reference ?? request()->reference;

        if (!$reference) {
            throw new IsNullException('Transaction reference is required');
        }

        return $this->makeRequest('GET', "/checkout/payments/merchant-reference/{$reference}");
    }

    /**
     * Get Webhook Data
     *
     * @return $this
     */
    public function getWebhookData()
    {
        return $this;
    }

    /**
     * Process Webhook Data
     *
     * @param callable $callback
     * @return void
     */
    public function processData(callable $callback)
    {
        $payload = request()->getContent();
        $signature = request()->header('X-Fincra-Signature');

        // Verify webhook signature
        if (!$this->verifyWebhookSignature($payload, $signature)) {
            throw new \Exception('Invalid webhook signature');
        }

        $data = json_decode($payload, true);
        
        call_user_func($callback, $data);
    }

    /**
     * Verify Webhook Signature
     *
     * @param string $payload
     * @param string $signature
     * @return bool
     */
    protected function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        if (!$signature) {
            return false;
        }

        $webhookSecret = Config::get('fincra.webhookSecret');
        $computedSignature = hash_hmac('sha512', $payload, $webhookSecret);

        return hash_equals($computedSignature, $signature);
    }

    /**
     * Create Virtual Account
     *
     * @param array $data
     * @return array
     */
    public function createVirtualAccount(array $data)
    {
        return $this->makeRequest('POST', '/virtual-accounts', $data);
    }

    /**
     * Get Virtual Accounts
     *
     * @return array
     */
    public function getVirtualAccounts()
    {
        return $this->makeRequest('GET', '/virtual-accounts');
    }

    /**
     * Get Single Virtual Account
     *
     * @param string $id
     * @return array
     */
    public function getVirtualAccount(string $id)
    {
        return $this->makeRequest('GET', "/virtual-accounts/{$id}");
    }

    /**
     * Get Virtual Account Requests
     *
     * @return array
     */
    public function getVirtualAccountRequests()
    {
        return $this->makeRequest('GET', '/virtual-accounts/requests');
    }

    /**
     * Update Virtual Account
     *
     * @param string $id
     * @param array $data
     * @return array
     */
    public function updateVirtualAccount(string $id, array $data)
    {
        return $this->makeRequest('PATCH', "/virtual-accounts/{$id}", $data);
    }

    /**
     * Delete Virtual Account
     *
     * @param string $id
     * @return array
     */
    public function deleteVirtualAccount(string $id)
    {
        return $this->makeRequest('DELETE', "/virtual-accounts/{$id}");
    }

    /**
     * Initiate Payout
     *
     * @param array $data
     * @return array
     */
    public function initiatePayout(array $data)
    {
        return $this->makeRequest('POST', '/payouts', $data);
    }

    /**
     * Get Payouts
     *
     * @return array
     */
    public function getPayouts()
    {
        return $this->makeRequest('GET', '/payouts');
    }

    /**
     * Get Single Payout
     *
     * @param string $id
     * @return array
     */
    public function getPayout(string $id)
    {
        return $this->makeRequest('GET', "/payouts/{$id}");
    }

    /**
     * Get Payout by Reference
     *
     * @param string $reference
     * @return array
     */
    public function getPayoutByReference(string $reference)
    {
        return $this->makeRequest('GET', "/payouts/reference/{$reference}");
    }

    /**
     * Cancel Payout
     *
     * @param string $id
     * @return array
     */
    public function cancelPayout(string $id)
    {
        return $this->makeRequest('POST', "/payouts/{$id}/cancel");
    }

    /**
     * Create Beneficiary
     *
     * @param array $data
     * @return array
     */
    public function createBeneficiary(array $data)
    {
        return $this->makeRequest('POST', '/beneficiaries', $data);
    }

    /**
     * Get Beneficiaries
     *
     * @return array
     */
    public function getBeneficiaries()
    {
        return $this->makeRequest('GET', '/beneficiaries');
    }

    /**
     * Get Single Beneficiary
     *
     * @param string $id
     * @return array
     */
    public function getBeneficiary(string $id)
    {
        return $this->makeRequest('GET', "/beneficiaries/{$id}");
    }

    /**
     * Update Beneficiary
     *
     * @param string $id
     * @param array $data
     * @return array
     */
    public function updateBeneficiary(string $id, array $data)
    {
        return $this->makeRequest('PATCH', "/beneficiaries/{$id}", $data);
    }

    /**
     * Delete Beneficiary
     *
     * @param string $id
     * @return array
     */
    public function deleteBeneficiary(string $id)
    {
        return $this->makeRequest('DELETE', "/beneficiaries/{$id}");
    }

    /**
     * Verify BVN
     *
     * @param string $bvn
     * @return array
     */
    public function verifyBvn(string $bvn)
    {
        return $this->makeRequest('GET', "/verification/bvn/{$bvn}");
    }

    /**
     * Verify Bank Account
     *
     * @param array $data
     * @return array
     */
    public function verifyBankAccount(array $data)
    {
        return $this->makeRequest('POST', '/verification/account', $data);
    }

    /**
     * Verify IBAN
     *
     * @param string $iban
     * @return array
     */
    public function verifyIban(string $iban)
    {
        return $this->makeRequest('GET', "/verification/iban/{$iban}");
    }

    /**
     * Resolve BIN
     *
     * @param string $bin
     * @return array
     */
    public function resolveBin(string $bin)
    {
        return $this->makeRequest('GET', "/verification/bin/{$bin}");
    }

    /**
     * Get Balance
     *
     * @param string|null $currency
     * @return array
     */
    public function getBalance(?string $currency = null)
    {
        $endpoint = $currency ? "/wallets/balance?currency={$currency}" : '/wallets/balance';
        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Get Transactions
     *
     * @return array
     */
    public function getTransactions()
    {
        return $this->makeRequest('GET', '/transactions');
    }

    /**
     * Get Single Transaction
     *
     * @param string $id
     * @return array
     */
    public function getTransaction(string $id)
    {
        return $this->makeRequest('GET', "/transactions/{$id}");
    }

    /**
     * Create Conversion
     *
     * @param array $data
     * @return array
     */
    public function createConversion(array $data)
    {
        return $this->makeRequest('POST', '/conversions', $data);
    }

    /**
     * Get Conversions
     *
     * @return array
     */
    public function getConversions()
    {
        return $this->makeRequest('GET', '/conversions');
    }

    /**
     * Get Conversion Rate
     *
     * @param string $from
     * @param string $to
     * @return array
     */
    public function getConversionRate(string $from, string $to)
    {
        return $this->makeRequest('GET', "/conversions/rate?from={$from}&to={$to}");
    }

    /**
     * Get Banks
     *
     * @param string|null $country
     * @return array
     */
    public function getBanks(?string $country = null)
    {
        $endpoint = $country ? "/banks?country={$country}" : '/banks';
        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Get Currencies
     *
     * @return array
     */
    public function getCurrencies()
    {
        return $this->makeRequest('GET', '/currencies');
    }

    /**
     * Get Countries
     *
     * @return array
     */
    public function getCountries()
    {
        return $this->makeRequest('GET', '/countries');
    }

    /**
     * Validate Checkout Data
     *
     * @param array $data
     * @throws IsNullException
     */
    protected function validateCheckoutData(array $data)
    {
        if (!isset($data['amount'])) {
            throw new IsNullException('Amount is required');
        }

        $email = $data['email'] ?? $data['customer']['email'] ?? $data['customerEmail'] ?? null;
        if (!$email) {
            throw new IsNullException('Customer email is required');
        }

        $name = $data['name'] ?? $data['customer']['name'] ?? $data['customerName'] ?? null;
        if (!$name) {
            throw new IsNullException('Customer name is required');
        }
    }

    /**
     * Generate Unique Reference
     *
     * @return string
     */
    protected function generateReference(): string
    {
        return 'fincra_' . uniqid() . '_' . time();
    }
}