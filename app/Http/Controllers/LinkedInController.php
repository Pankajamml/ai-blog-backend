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
            // Get LinkedIn user ID
            $profileResponse = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                ])
                ->get('https://api.linkedin.com/v2/userinfo');

            $profile = $profileResponse->json();
            Log::info('LinkedIn Profile:', $profile);

            $userId = $profile['sub'] ?? null;

            if (!$userId) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Could not get LinkedIn user ID',
                ], 500);
            }

            // Clean content
            $content = strip_tags($blog->content);
            $content = substr($content, 0, 2900);

            // Publish post
            $postResponse = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization'             => 'Bearer ' . $token,
                    'Content-Type'              => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0',
                ])
                ->post('https://api.linkedin.com/v2/ugcPosts', [
                    'author'          => 'urn:li:person:' . $userId,
                    'lifecycleState'  => 'PUBLISHED',
                    'specificContent' => [
                        'com.linkedin.ugc.ShareContent' => [
                            'shareCommentary' => [
                                'text' => $content
                            ],
                            'shareMediaCategory' => 'NONE',
                        ]
                    ],
                    'visibility' => [
                        'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                    ],
                ]);

            $postData = $postResponse->json();
            Log::info('LinkedIn Post Response:', $postData);

            if (isset($postData['id'])) {
                // Update blog status
                $blog->update([
                    'status'           => 'published',
                    'linkedin_post_id' => $postData['id'],
                    'published_at'     => now(),
                ]);

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Blog published to LinkedIn!',
                    'post_id' => $postData['id'],
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'Publish failed!',
                'raw'     => $postData,
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}