<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function stats()
    {
        // Total counts
        $total     = Blog::count();
        $published = Blog::where('status', 'published')->count();
        $drafts    = Blog::where('status', 'draft')->count();

        // By platform
        $byPlatform = Blog::select('platform', DB::raw('count(*) as count'))
            ->groupBy('platform')
            ->get();

        // By tone
        $byTone = Blog::select('tone', DB::raw('count(*) as count'))
            ->groupBy('tone')
            ->get();

        // Recent blogs
        $recent = Blog::latest()
            ->take(5)
            ->get(['id', 'topic', 'platform', 'status', 'created_at']);

        // Blogs per day (last 7 days)
        $perDay = Blog::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count')
            )
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'total'      => $total,
            'published'  => $published,
            'drafts'     => $drafts,
            'byPlatform' => $byPlatform,
            'byTone'     => $byTone,
            'recent'     => $recent,
            'perDay'     => $perDay,
        ]);
    }
}