<?php

namespace App\Modules\Idea\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Idea\Services\NewsService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public face of a single Novidades entry — for crawlers, and for the click that follows.
 *
 * A share link has to answer two very different callers with one URL. WhatsApp and friends fetch
 * it, read the head, and never run a line of JavaScript; the SPA cannot serve them, because every
 * one of its routes returns the same index.html with the same picture. A person, meanwhile, wants
 * the article — which lives behind a session, in the app.
 *
 * So this returns a page whose head is already true, and whose body sends the person on.
 */
class NewsShareController extends Controller
{
    /*
     * The query key the SPA reads to open an entry (`SHARED_ENTRY_QUERY_KEY` in app.rnc's NewsView).
     * Portuguese on purpose: it is typed into a chat and read by people, like the `/n/` path.
     */
    private const APP_ENTRY_QUERY_KEY = 'entrada';

    public function __construct(private NewsService $newsService) {}

    public function show(int $id): View
    {
        $news = $this->newsService->findShareable($id);

        if (! $news) {
            throw new NotFoundHttpException;
        }

        return view('share.news', [
            'news' => $news,
            'coverUrl' => $news->cover?->url(),
            'shareUrl' => url("/n/{$news->id}"),
            'appUrl' => config('rnc.frontend_url').'/news?'.self::APP_ENTRY_QUERY_KEY."={$news->id}",
        ]);
    }
}
