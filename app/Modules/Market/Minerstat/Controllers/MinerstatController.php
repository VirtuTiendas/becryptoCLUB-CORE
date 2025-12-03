<?php

namespace App\Modules\Market\Minerstat\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MinerstatController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'provider' => 'Minerstat',
            'message' => 'Market data placeholder.'
        ]);
    }
}
