<?php

namespace App\Modules\Mining\Sha3\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Sha3Controller extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'module' => 'Sha3',
            'message' => 'Network stats endpoint placeholder.'
        ]);
    }
}
