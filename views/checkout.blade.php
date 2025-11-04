<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Fincra Checkout</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #3e0cb1ff 0%, #764ba2 100%);
        }

        .loading-container {
            text-align: center;
            color: white;
        }

        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 4px solid white;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            text-align: center;
        }

        .error-container h2 {
            color: #e74c3c;
            margin-top: 0;
        }

        .error-container p {
            color: #666;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="loading-container">
        <div class="spinner"></div>
        <h2>Initializing Payment...</h2>
        <p>Please wait to complete your payment</p>
    </div>

    <script src="{{ config('fincra.checkoutJs') }}"></script>
    <script>
        // Payment configuration
        const paymentConfig = {
            key: "{{ $publicKey }}",
            amount: parseInt("{{ $amount }}"),
            currency: "{{ $currency }}",
            customer: {
                name: "{{ $customerName }}",
                email: "{{ $customerEmail }}",
                @if($customerPhone)
                phoneNumber: "{{ $customerPhone }}"
                @endif
            },
            feeBearer: "{{ $feeBearer }}",
            @if($paymentMethods)
            paymentMethods: {!! json_encode($paymentMethods) !!},
            @endif
            @if($metadata && count($metadata) > 0)
            metadata: {!! json_encode($metadata) !!},
            @endif
            onClose: function() {
                console.log('Payment window closed');
                showError('Payment Cancelled', 'You closed the payment window. Please try again if you wish to complete the payment.');
            },
            onSuccess: function(data) {
                console.log('Payment successful:', data);
                const reference = data.reference || data.merchantReference;
                
                if (reference) {
                    window.location.href = "{{ $callbackUrl }}?reference=" + reference + "&status=success";
                } else {
                    showError('Payment Completed', 'Payment was successful but we could not get the reference. Please contact support.');
                }
            },
            onError: function(error) {
                console.error('Payment error:', error);
                showError('Payment Failed', error.message || 'An error occurred while processing your payment. Please try again.');
            }
        };

        // Initialize payment
        document.addEventListener('DOMContentLoaded', function() {
            try {
                if (typeof Fincra === 'undefined') {
                    throw new Error('Fincra SDK not loaded');
                }
                
                setTimeout(function() {
                    Fincra.initialize(paymentConfig);
                }, 500);
            } catch (error) {
                console.error('Initialization error:', error);
                showError('Initialization Failed', 'Could not initialize payment. Please refresh the page and try again.');
            }
        });

        // Show error message
        function showError(title, message) {
            const container = document.querySelector('.loading-container');
            container.innerHTML = `
                <div class="error-container">
                    <h2>${title}</h2>
                    <p>${message}</p>
                    <a href="javascript:history.back()" class="btn">Go Back</a>
                </div>
            `;
        }

        // Handle SDK load error
        window.addEventListener('error', function(e) {
            if (e.filename && e.filename.includes('fincra')) {
                showError('Payment System Unavailable', 'Could not load the payment system. Please check your internet connection and try again.');
            }
        });
    </script>
</body>
</html>