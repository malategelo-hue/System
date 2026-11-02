<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index()
    {
        $subjects = [
            ['id' => 1, 'name' => 'Sample Item 1', 'description' => 'Description here', 'quantity' => 50],
            ['id' => 2, 'name' => 'Sample Item 2', 'description' => 'Another item', 'quantity' => 30],
            ['id' => 3, 'name' => 'Sample Item 3', 'description' => 'Stock item', 'quantity' => 100],
        ];
        return view('subjects.index', compact('subjects'));
    }

    public function create()
    {
        return view('subjects.create');
    }

    public function store(Request $request)
    {
        return redirect()->route('subjects.index')->with('success', 'Item added successfully!');
    }
}