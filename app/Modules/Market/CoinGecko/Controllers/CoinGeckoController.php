<?php

namespace App\Modules\Market\CoinGecko\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CoinGeckoController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'provider' => 'CoinGecko',
            'message' => 'Market data placeholder.'
        ]);
    }
}
