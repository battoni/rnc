<?php

namespace App\Modules\Idea\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNewsRequest extends FormRequest
{
    use NewsRequestRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->newsFieldRules() + [
            /*
             * REQUIRED on update, unlike create, and the asymmetry is the point.
             *
             * The service writes `is_published` from this field unconditionally, so when it was
             * `nullable` a perfectly valid request that simply omitted it pulled a live article
             * off every reader's list. Absent cannot mean "unpublish". Either say which state you
             * want, or use `PATCH /news/{id}/publish`, which exists precisely so that flipping the
             * switch does not require resending the article.
             */
            'publish' => ['required', 'boolean'],
            /*
             * Update-only, and deliberately absent from the create rules: a brand-new entry has
             * nothing to clear, so `areas` absent and `areas` empty mean the same thing there.
             * On an edit they do not — see `NewsService::syncAreas`.
             */
            'clearAreas' => ['nullable', 'boolean'],
        ];
    }
}
