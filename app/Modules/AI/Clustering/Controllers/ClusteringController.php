<?php

namespace App\Modules\AI\Clustering\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ClusteringController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'module' => 'Clustering',
            'message' => 'AI pipeline placeholder.'
        ]);
    }
}
