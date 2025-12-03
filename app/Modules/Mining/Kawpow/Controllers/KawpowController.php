<?php

namespace App\Modules\Mining\Kawpow\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class KawpowController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'module' => 'Kawpow',
            'message' => 'Network stats endpoint placeholder.'
        ]);
    }
}
