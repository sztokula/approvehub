<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders internal project documentation pages from repository markdown files.
 */
class ProjectDocsController extends Controller
{
    public function show(string $slug): View
    {
        $map = [
            'documentation' => 'DOCUMENTATION.MD',
            'changelog' => 'CHANGELOG.md',
            'what-i-learn' => 'WHAT_I_LEARN.md',
        ];

        $file = $map[$slug] ?? null;

        if ($file === null || ! file_exists(base_path($file))) {
            throw new NotFoundHttpException();
        }

        $markdown = file_get_contents(base_path($file));
        $html = Str::markdown((string) $markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return view('project-docs.show', [
            'title' => Str::headline(str_replace('.md', '', strtolower($file))),
            'slug' => $slug,
            'html' => $html,
        ]);
    }
}
