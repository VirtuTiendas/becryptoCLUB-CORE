<?php

namespace App\Modules\AI\Insights\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class InsightsController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'module' => 'Insights',
            'message' => 'AI pipeline placeholder.'
        ]);
    }
}
