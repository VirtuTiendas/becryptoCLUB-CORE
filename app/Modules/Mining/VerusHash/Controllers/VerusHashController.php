<?php

namespace App\Modules\Mining\VerusHash\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VerusHashController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'module' => 'VerusHash',
            'message' => 'Network stats endpoint placeholder.'
        ]);
    }
}
