<?php

namespace App\Modules\AI\MarketSummary\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MarketSummaryController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'module' => 'MarketSummary',
            'message' => 'AI pipeline placeholder.'
        ]);
    }
}
