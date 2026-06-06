<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BlogController extends Controller
{
    
    public function generate(Request $request)
    {
        $request->validate([
            'topic'    => 'required|string|max:255',
            'platform' => 'required|in:linkedin,medium,both',
            'tone'     => 'required|in:professional,casual,technical',
            'scheduled_at' => 'nullable|date',
        ]);

        $topic    = $request->input('topic');
        $platform = $request->input('platform');
        $tone     = $request->input('tone');

        // Build prompt
        $prompt = $this->buildPrompt($topic, $platform, $tone);

        // Call Groq API
        $response = Http::withoutVerifying()
            ->withHeaders([
                'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
                'Content-Type'  => 'application/json',
            ])
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    [
                        'role'    => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens'  => 1024,
                'temperature' => 0.7,
            ]);

        $data = $response->json();

        // Debug log
        \Log::info('Groq Response:', $data);

        // Handle API errors
        if (isset($data['error'])) {
            return response()->json([
                'status'  => 'error',
                'message' => $data['error']['message'],
            ], 500);
        }

        // Check content exists
        if (!isset($data['choices'][0]['message']['content'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No content generated',
                'raw'     => $data,
            ], 500);
        }

        $content = $data['choices'][0]['message']['content'];

        // Save to DB
        $blog = Blog::create([
            'topic'          => $topic,
    'content'        => $content,
    'platform'       => $platform,
    'tone'           => $tone,
    'image_url'      => $request->input('image_url', null),
    'scheduled_at'   => $request->input('scheduled_at', null),
    'linkedin_token' => $request->input('linkedin_token', null),
    'status'         => $request->input('scheduled_at') ? 'scheduled' : 'draft',
        ]);

        return response()->json([
            'status'   => 'success',
            'blog_id'  => $blog->id,
            'topic'    => $topic,
            'content'  => $content,
            'platform' => $platform,
            'image_url' => $request->input('image_url', null),
            'scheduled_at' => $request->input('scheduled_at', null),
        ]);
    }
    // Get all blogs
public function index()
{
     $blogs = Blog::latest()->select(
        'id',
        'topic',
        'content',
        'platform',
        'tone',
        'status',
        'image_url',
        'scheduled_at',
        'created_at'
    )->get();

    return response()->json($blogs);
}
    
    // Build prompt
    private function buildPrompt($topic, $platform, $tone)
    {
        $toneText = match($tone) {
            'professional' => 'professional and formal',
            'casual'       => 'casual and friendly',
            'technical'    => 'technical and detailed',
            default        => 'professional'
        };

        if ($platform === 'linkedin') {
            return "Write a {$toneText} LinkedIn blog post about: {$topic}.
                    - Start with a strong hook
                    - Add 3 key points
                    - Use short paragraphs
                    - End with call to action
                    - Add relevant hashtags";
        }

        if ($platform === 'medium') {
            return "Write a {$toneText} Medium article about: {$topic}.
                    - Write a compelling title
                    - Add introduction
                    - Add 5 detailed sections
                    - Add conclusion
                    - Make it 800-1000 words";
        }

        return "Write a {$toneText} blog post about: {$topic}.
                Make it suitable for both LinkedIn and Medium.
                Add 3 key points and end with call to action.";
    }
}