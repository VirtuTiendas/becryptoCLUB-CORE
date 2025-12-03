<?php

namespace App\Modules\AI\MiningHealth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MiningHealthController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'module' => 'MiningHealth',
            'message' => 'AI pipeline placeholder.'
        ]);
    }
}
