<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A paginator, as an Inertia page prop.
 *
 * One definition, because every index page needs the same three things and a
 * page assembling them itself would be free to differ. The status line and the
 * page labels are formatted here rather than in Vue, per spec §5a.
 *
 * **The window is this app's, not Laravel's.** The Blade view this replaces
 * says so explicitly: "The page numbers are windowed here rather than taken
 * from $elements. Laravel's own window cannot produce the shape this app wants:
 * under eight pages it renders every one of them, and over that its slider
 * widens around the current page." So `linkCollection()` is NOT used -- it
 * returns exactly the elements the Blade deliberately ignores, and reaching for
 * it would quietly change the shape of every pager in the app.
 */
class Paginate
{
    /**
     * @template T
     *
     * @param  LengthAwarePaginator<T>  $paginator
     * @param  callable(T): array<string, mixed>  $each  turns one row into its props
     * @return array<string, mixed>
     */
    public static function props(LengthAwarePaginator $paginator, callable $each): array
    {
        return [
            'data' => collect($paginator->items())->map($each)->values()->all(),
            'links' => self::links($paginator),

            // Null on a single page, so the template has nothing to decide --
            // the same condition the Blade view opens with.
            'status_label' => $paginator->hasPages()
                ? __('Showing :first to :last of :total', [
                    'first' => $paginator->firstItem(),
                    'last' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ])
                : null,
        ];
    }

    /**
     * Previous, the windowed page numbers with their gaps, and Next.
     *
     * Each link says what KIND it is rather than leaving the template to infer
     * it from a label. Laravel's own labels carry HTML entities -- "&laquo;
     * Previous" -- and a template deciding "is this the previous link?" by
     * looking for the word would break the first time it was translated.
     *
     * @param  LengthAwarePaginator<mixed>  $paginator
     * @return list<array<string, mixed>>
     */
    private static function links(LengthAwarePaginator $paginator): array
    {
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();

        $links = [[
            'kind' => 'previous',
            'url' => $paginator->previousPageUrl(),
            'label' => __('Previous'),
            'active' => false,
            'aria_label' => null,
        ]];

        /*
         * The first two, the last two, and the page you are on.
         *
         * The current page earns its place: without it nothing in the row is
         * marked, and on page 5 of 10 the control would show "1 2 ... 9 10"
         * with no indication of where you are. Its neighbours are deliberately
         * left out -- Previous and Next reach them, and the point of this
         * window is to stay short.
         *
         * unique() matters at the small end: on three pages the set is
         * 1, 2, 2, 3 before it is deduped, and on two pages $last - 1 is 1.
         */
        $pages = collect([1, 2, $current, $last - 1, $last])
            ->filter(fn ($page) => $page >= 1 && $page <= $last)
            ->unique()
            ->sort()
            ->values();

        $previousPage = null;

        foreach ($pages as $page) {
            // A gap only where one was actually skipped, so a run of
            // consecutive pages never grows a separator.
            if ($previousPage !== null && $page - $previousPage > 1) {
                $links[] = [
                    'kind' => 'gap',
                    'url' => null,
                    'label' => '…',
                    'active' => false,
                    'aria_label' => null,
                ];
            }

            $links[] = [
                'kind' => 'page',
                'url' => $page === $current ? null : $paginator->url($page),
                'label' => (string) $page,
                'active' => $page === $current,
                'aria_label' => $page === $current
                    ? null
                    : __('Go to page :page', ['page' => $page]),
            ];

            $previousPage = $page;
        }

        $links[] = [
            'kind' => 'next',
            'url' => $paginator->nextPageUrl(),
            'label' => __('Next'),
            'active' => false,
            'aria_label' => null,
        ];

        return $links;
    }
}
