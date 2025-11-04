<?php

return [
    /**
     * Public Key From Fincra Dashboard
     */
    'publicKey' => getenv('FINCRA_PUBLIC_KEY'),

    /**
     * Secret Key From Fincra Dashboard
     */
    'secretKey' => getenv('FINCRA_SECRET_KEY'),

    /**
     * Business ID From Fincra Dashboard
     */
    'businessId' => getenv('FINCRA_BUSINESS_ID'),

    /**
     * Webhook Secret for verifying webhooks
     */
    'webhookSecret' => getenv('FINCRA_WEBHOOK_SECRET'),

    /**
     * Environment: sandbox or live
     */
    'env' => env('FINCRA_ENV', 'sandbox'),

    /**
     * Fincra Sandbox URL
     */
    'sandboxUrl' => env('FINCRA_SANDBOX_URL', 'https://sandboxapi.fincra.com'),

    /**
     * Fincra Live URL
     */
    'liveUrl' => env('FINCRA_LIVE_URL', 'https://api.fincra.com'),

    /**
     * Fincra Checkout Inline JS URL
     */
    'checkoutJs' => 'https://unpkg.com/@fincra-engineering/[email protected]/dist/inline.min.js',
];
