<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComponentData;
use Illuminate\Http\Request;

class ComponentDataController extends Controller
{
    public function index()
    {
        $data = ComponentData::all();
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'status' => 'sometimes|string|in:active,inactive'
        ]);

        $data = ComponentData::create($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Data created successfully',
            'data' => $data
        ], 201);
    }

    public function show($id)
    {
        $data = ComponentData::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = ComponentData::findOrFail($id);
        
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'status' => 'sometimes|string|in:active,inactive'
        ]);

        $data->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Data updated successfully',
            'data' => $data
        ]);
    }

    public function destroy($id)
    {
        $data = ComponentData::findOrFail($id);
        $data->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully'
        ]);
    }
}