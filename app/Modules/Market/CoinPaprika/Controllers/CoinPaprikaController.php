<?php

namespace App\Modules\Market\CoinPaprika\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CoinPaprikaController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'provider' => 'CoinPaprika',
            'message' => 'Market data placeholder.'
        ]);
    }
}
