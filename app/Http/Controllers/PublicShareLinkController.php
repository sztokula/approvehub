<?php

namespace App\Http\Controllers;

use App\Models\PublicShareLink;
use Illuminate\Contracts\View\View;

/**
 * Handles PublicShareLinkController responsibilities for the ApproveHub domain.
 */
class PublicShareLinkController extends Controller
{
    public function show(PublicShareLink $publicShareLink): View
    {
        abort_unless($publicShareLink->isAccessible(), 404);

        $publicShareLink->load([
            'document.owner:id,name',
            'document.currentVersion:id,document_id,version_number,title_snapshot,content_snapshot,created_at',
            'document.currentVersion.creator:id,name',
        ]);

        return view('public-share-links.show', [
            'publicShareLink' => $publicShareLink,
            'document' => $publicShareLink->document,
            'currentVersion' => $publicShareLink->document->currentVersion,
        ]);
    }
}
