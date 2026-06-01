<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        try {
            $image    = $request->file('image');
            $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();

            // Upload to S3
            $path = Storage::disk('s3')->putFileAs(
                'blog-images',
                $image,
                $filename,
                'public'
            );

            $url = Storage::disk('s3')->url($path);

            return response()->json([
                'status'   => 'success',
                'url'      => $url,
                'filename' => $filename,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        $request->validate([
            'filename' => 'required|string',
        ]);

        try {
            Storage::disk('s3')->delete('blog-images/' . $request->filename);

            return response()->json([
                'status'  => 'success',
                'message' => 'Image deleted!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}