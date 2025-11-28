<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function stockIn(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $product->increment('stock', $validated['quantity']);

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => $validated['quantity'],
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json(['message' => 'Stock-in successful', 'movement' => $movement], 201);
    }

    public function stockOut(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        // 🛡️ Validation: prevent stock going negative
        if ($product->stock < $validated['quantity']) {
            return response()->json([
                'message' => 'Not enough stock available',
                'available_stock' => $product->stock
            ], 400);
        }

        $product->decrement('stock', $validated['quantity']);

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => $validated['quantity'],
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'message' => 'Stock-out successful',
            'movement' => $movement
        ], 201);
    }

    public function adjustStock(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'new_stock' => 'required|integer|min:0',
            'note' => 'nullable|string',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $oldStock = $product->stock;
        $newStock = $validated['new_stock'];
        $difference = $newStock - $oldStock;

        // Only log adjustment if there is a change
        if ($difference === 0) {
            return response()->json(['message' => 'No stock adjustment needed. Stock is already at this value.'], 200);
        }

        $product->stock = $newStock;
        $product->save();

        StockMovement::create([
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => abs($difference),
            'note' => $validated['note'] ?? 'Manual stock adjustment from ' . $oldStock . ' to ' . $newStock,
        ]);

        return response()->json([
            'message' => 'Stock adjusted successfully',
            'old_stock' => $oldStock,
            'new_stock' => $newStock
        ], 200);
    }
}
