<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\Blog;

#[Signature('app:publish-scheduled-blogs')]
#[Description('Command description')]

class PublishScheduledBlogs extends Command
{
    protected $signature = 'blogs:publish';
    protected $description = 'Publish scheduled blogs';

     public function handle()
    {
        $blogs = Blog::where('is_published', false)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($blogs as $blog) {

            $blog->update([
                'is_published' => true,
                'published_at' => now(),
                'status' => 'published'
            ]);

            $this->info("Published Blog ID: {$blog->id}");
        }

        return Command::SUCCESS;
    }
}
