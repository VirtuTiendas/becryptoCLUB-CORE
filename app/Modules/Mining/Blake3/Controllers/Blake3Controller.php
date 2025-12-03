<?php

namespace App\Modules\Mining\Blake3\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Blake3Controller extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'module' => 'Blake3',
            'message' => 'Network stats endpoint placeholder.'
        ]);
    }
}
