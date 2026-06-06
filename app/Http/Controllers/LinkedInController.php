<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkedInController extends Controller
{
    // Exchange code for token
    public function exchange(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        try {
            // Exchange code for access token
            $tokenResponse = Http::withoutVerifying()
                ->asForm()
                ->post('https://www.linkedin.com/oauth/v2/accessToken', [
                    'grant_type'    => 'authorization_code',
                    'code'          => $request->code,
                    'redirect_uri'  => env('LINKEDIN_REDIRECT_URI'),
                    'client_id'     => env('LINKEDIN_CLIENT_ID'),
                    'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
                ]);

            $tokenData = $tokenResponse->json();
            Log::info('LinkedIn Token Response:', $tokenData);

            // Check token received
            if (!isset($tokenData['access_token'])) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Failed to get token',
                    'raw'     => $tokenData,
                ], 500);
            }

            $accessToken = $tokenData['access_token'];

            // Get user profile
            $profileResponse = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                ])
                ->get('https://api.linkedin.com/v2/userinfo');

            $profile = $profileResponse->json();
            Log::info('LinkedIn Profile:', $profile);

            return response()->json([
                'status' => 'success',
                'token'  => $accessToken,
                'name'   => $profile['name'] ?? 'LinkedIn User',
                'id'     => $profile['sub']  ?? '',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Publish blog to LinkedIn
   public function publish(Request $request)
{
    $request->validate([
        'blog_id' => 'required|integer',
        'token'   => 'required|string',
    ]);

    $blog  = Blog::findOrFail($request->blog_id);
    $token = $request->token;

    try {
        // Get user ID
        $profile = Http::withoutVerifying()
            ->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('https://api.linkedin.com/v2/userinfo')
            ->json();

        $userId = $profile['sub'] ?? null;
        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'No user ID'], 500);
        }

        $content = substr(strip_tags($blog->content), 0, 2900);
        $authorUrn = 'urn:li:person:' . $userId;

        // If blog has image, upload it to LinkedIn first
        $imageAsset = null;
        if ($blog->image_url) {
            $imageAsset = $this->uploadImageToLinkedIn($token, $authorUrn, $blog->image_url);
        }

        // Build post body
        if ($imageAsset) {
            // Post WITH image
            $postBody = [
                'author'         => $authorUrn,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary'    => ['text' => $content],
                        'shareMediaCategory' => 'IMAGE',
                        'media' => [[
                            'status'      => 'READY',
                            'description' => ['text' => $blog->topic],
                            'media'       => $imageAsset,
                            'title'       => ['text' => $blog->topic],
                        ]],
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ],
            ];
        } else {
            // Post WITHOUT image
            $postBody = [
                'author'         => $authorUrn,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary'    => ['text' => $content],
                        'shareMediaCategory' => 'NONE',
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ],
            ];
        }

        $postResponse = Http::withoutVerifying()
            ->withHeaders([
                'Authorization'             => 'Bearer ' . $token,
                'Content-Type'              => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0',
            ])
            ->post('https://api.linkedin.com/v2/ugcPosts', $postBody);

        $postData = $postResponse->json();
        \Log::info('LinkedIn Post Response:', $postData);

        if (isset($postData['id'])) {
            $blog->update([
                'status'           => 'published',
                'linkedin_post_id' => $postData['id'],
                'published_at'     => now(),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Published to LinkedIn!' . ($imageAsset ? ' (with image)' : ''),
                'post_id' => $postData['id'],
            ]);
        }

        return response()->json([
            'status'  => 'error',
            'message' => 'Publish failed',
            'raw'     => $postData,
        ], 500);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}

// Helper: Upload image to LinkedIn
private function uploadImageToLinkedIn($token, $authorUrn, $imageUrl)
{
    try {
        // Step 1: Register upload
        $register = Http::withoutVerifying()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ])
            ->post('https://api.linkedin.com/v2/assets?action=registerUpload', [
                'registerUploadRequest' => [
                    'recipes'    => ['urn:li:digitalmediaRecipe:feedshare-image'],
                    'owner'      => $authorUrn,
                    'serviceRelationships' => [[
                        'relationshipType' => 'OWNER',
                        'identifier'       => 'urn:li:userGeneratedContent',
                    ]],
                ]
            ])
            ->json();

        $uploadUrl = $register['value']['uploadMechanism']
            ['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'] ?? null;
        $asset = $register['value']['asset'] ?? null;

        if (!$uploadUrl || !$asset) {
            \Log::error('LinkedIn image register failed', $register);
            return null;
        }

        // Step 2: Download image from S3
        $imageData = Http::withoutVerifying()->get($imageUrl)->body();

        // Step 3: Upload bytes to LinkedIn
        Http::withoutVerifying()
            ->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBody($imageData, 'application/octet-stream')
            ->post($uploadUrl);

        return $asset;

    } catch (\Exception $e) {
        \Log::error('LinkedIn image upload error: ' . $e->getMessage());
        return null;
    }
}
}