<?php

namespace App\Modules\Mining\Scrypt\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ScryptController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'module' => 'Scrypt',
            'message' => 'Network stats endpoint placeholder.'
        ]);
    }
}
