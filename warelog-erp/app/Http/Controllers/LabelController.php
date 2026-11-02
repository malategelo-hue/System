<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

/**
 * Printable QR label sheets for inventory items.
 */
class LabelController extends Controller
{
    public function show(Request $request, Item $item)
    {
        $copies = (int) $request->query('copies', 1);
        $copies = max(1, min(200, $copies));

        return view('labels.sheet', [
            'item' => $item,
            'copies' => $copies,
            'systemSettings' => SystemSetting::values(),
        ]);
    }
}
