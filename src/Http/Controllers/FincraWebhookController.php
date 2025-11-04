<?php

namespace PrevailExcel\Fincra\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FincraWebhookController extends Controller
{
    /**
     * Handle the webhook
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        fincra()->getWebhookData()->processData(function ($data) {
            // Log the webhook data
            logger('Fincra Webhook Received:', $data);
            
            // Dispatch event or job
            // event(new FincraWebhookReceived($data));
        });

        return response()->json(['status' => 'success'], 200);
    }
}
