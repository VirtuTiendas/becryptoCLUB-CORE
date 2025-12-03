<?php

namespace App\Modules\Mining\IronFish\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IronFishController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'module' => 'IronFish',
            'message' => 'Network stats endpoint placeholder.'
        ]);
    }
}
