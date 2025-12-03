<?php

namespace App\Modules\AI\Alerts\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AlertsController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'module' => 'Alerts',
            'message' => 'AI pipeline placeholder.'
        ]);
    }
}
