<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\Post;

class SyncObsidianPosts extends Command
{
    // Command details
    protected $signature = 'sync:obsidian-posts';
    protected $description = 'Sync Markdown files from Obsidian folder to Hugo content folder';

    public function handle()
    {
        // Define source and destination paths
        $obsidianPath = base_path('obsidian-posts'); // Folder where Obsidian files are saved
        $hugoPath = base_path('hugo-site/content/posts'); // Hugo content folder

        // Check if directories exist
        if (!File::exists($obsidianPath)) {
            $this->error("Obsidian folder does not exist at: $obsidianPath");
            return;
        }

        if (!File::exists($hugoPath)) {
            $this->error("Hugo content folder does not exist at: $hugoPath");
            return;
        }

        // Get all Markdown files from Obsidian folder
        $files = File::files($obsidianPath);

        if (empty($files)) {
            $this->info('No files found in Obsidian folder.');
            return;
        }

        foreach ($files as $file) {
            if ($file->getExtension() === 'md') {
                // Read the file content
                $content = File::get($file);

                // Parse the front matter and content
                $this->info("Processing: {$file->getFilename()}");
                $metadata = $this->parseFrontMatter($content);
                $body = $this->stripFrontMatter($content);

                if (!$metadata['title']) {
                    $this->error("File {$file->getFilename()} is missing a title. Skipping.");
                    continue;
                }

                // Generate a slug and filename
                $slug = Str::slug($metadata['title']);
                $filename = $slug . '.md';

                // Move the file to the Hugo folder
                $destination = $hugoPath . '/' . $filename;
                File::put($destination, $content);
                $this->info("Moved to: $destination");

                // Update or create the post in the database
                Post::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'title' => $metadata['title'],
                        'content' => $body,
                        'status' => $metadata['status'] ?? 'draft',
                    ]
                );

                // Optionally delete the file from Obsidian
                File::delete($file);
                $this->info("Deleted: {$file->getFilename()} from Obsidian folder");
            }
        }

        $this->info('Sync completed successfully!');
    }

    /**
     * Parse the front matter from Markdown content.
     */
    private function parseFrontMatter($content)
    {
        $metadata = [];
        if (preg_match('/^-{3}(.*?)^-{3}/s', $content, $matches)) {
            $frontMatter = yaml_parse(trim($matches[1]));
            $metadata = is_array($frontMatter) ? $frontMatter : [];
        }
        return $metadata;
    }

    /**
     * Strip the front matter from Markdown content.
     */
    private function stripFrontMatter($content)
    {
        return preg_replace('/^-{3}(.*?)^-{3}/s', '', $content, 1);
    }
}

