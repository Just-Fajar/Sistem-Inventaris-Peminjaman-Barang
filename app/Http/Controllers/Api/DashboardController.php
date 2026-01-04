<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Borrowing;
use App\Models\Category;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Total items
        $totalItems = Item::count();
        $availableItems = Item::where('available_stock', '>', 0)->count();
        $borrowedItems = Item::where('available_stock', '<', \DB::raw('stock'))
                             ->where('stock', '>', 0)
                             ->count();

        // Borrowing statistics
        $activeBorrowings = Borrowing::where('status', 'dipinjam')->count();
        $overdueBorrowings = Borrowing::where('status', 'terlambat')->count();
        $returnedBorrowings = Borrowing::where('status', 'dikembalikan')->count();

        // User-specific statistics (for staff)
        $myBorrowings = null;
        if ($user->isStaff()) {
            $myBorrowings = [
                'active' => Borrowing::where('user_id', $user->id)
                                    ->where('status', 'dipinjam')
                                    ->count(),
                'overdue' => Borrowing::where('user_id', $user->id)
                                     ->where('status', 'terlambat')
                                     ->count(),
                'total' => Borrowing::where('user_id', $user->id)->count(),
            ];
        }

        // Recent borrowings
        $recentBorrowings = Borrowing::with(['user', 'item'])
            ->when($user->isStaff(), function ($query) use ($user) {
                return $query->where('user_id', $user->id);
            })
            ->latest()
            ->take(5)
            ->get();

        // Items low in stock
        $lowStockItems = Item::with('category')
            ->where('available_stock', '<=', 5)
            ->where('available_stock', '>', 0)
            ->take(5)
            ->get();

        // Categories with item count
        $categories = Category::withCount('items')->take(10)->get();

        return response()->json([
            'items' => [
                'total' => $totalItems,
                'available' => $availableItems,
                'borrowed' => $borrowedItems,
            ],
            'borrowings' => [
                'active' => $activeBorrowings,
                'overdue' => $overdueBorrowings,
                'returned' => $returnedBorrowings,
            ],
            'my_borrowings' => $myBorrowings,
            'recent_borrowings' => $recentBorrowings,
            'low_stock_items' => $lowStockItems,
            'categories' => $categories,
        ]);
    }
}
