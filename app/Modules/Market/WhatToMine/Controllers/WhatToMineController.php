<?php

namespace App\Modules\Market\WhatToMine\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WhatToMineController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'provider' => 'WhatToMine',
            'message' => 'Market data placeholder.'
        ]);
    }
}
