<?php

namespace App\Modules\Mining\Aleo\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AleoController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'module' => 'Aleo',
            'message' => 'Network stats endpoint placeholder.'
        ]);
    }
}
