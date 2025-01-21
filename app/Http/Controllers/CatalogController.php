<?php

namespace App\Http\Controllers;

use App\Enum\CompanyJobStatusEnum;
use App\Enum\ReviewCanEditEnum;
use App\Enum\ReviewStatusEnum;
use App\Enum\SpecialistStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiPostUser;
use App\Models\CompanyJob;
use App\Models\Review;
use App\Models\Specialist;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    protected bool $onlyActive = false;

    public function specialists(Request $request)
    {
        $experienceList = Repositories::specialist()->getExperienceList($this->onlyActive);

        $validated = $request->validate([
            'key_word' => [
                'sometimes',
                'nullable',
                'string',
                'min:3',
                'max:50',
            ],
            'experience_id' => [
                'sometimes',
                'nullable',
                'string',
                'alpha_dash:ascii',
                Rule::in(array_keys($experienceList)),
            ],
        ]);

        $specialists = Specialist::with('user');

        if ($this->onlyActive) {
            $specialists = $specialists->where('status', '=', SpecialistStatusEnum::Active);
        }

        if (!empty($validated['key_word'])) {
            $specialists = $specialists->whereAny(['experience', 'about'], 'like', '%'.$validated['key_word'].'%');
        }

        if (!empty($validated['experience_id'])) {
            $specialists = $specialists->where('experience', $experienceList[$validated['experience_id']]['value']);
        }

        $specialists = $specialists
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('catalog.specialists', [
            'request'           => $request,
            'specialists'       => $specialists,
            'experienceList'    => $experienceList,
        ]);
    }

    public function specialistView(Request $request, string $id)
    {
        $validator = Validator::make($request->route()->parameters(), [
            'id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists(Specialist::table(), 'id')->where(function (Builder $query) {
                    if ($this->onlyActive) {
                        $query->where('status', SpecialistStatusEnum::Active);
                    }
                }),
            ],
        ]);

        if ($validator->fails()) {
            abort(404);
        }

        $validated = $validator->validateWithBag('specialist');

        $specialist = Specialist::where('id', $validated['id'])->firstOrFail();
        $reviews = $specialist->reviews()->where('status', ReviewStatusEnum::Active)->orderBy('created_at')->get();

        return view('catalog.specialist_view', [
            'request'          => $request,
            'specialist'       => $specialist,
            'reviews'          => $reviews,
        ]);
    }

    public function specialistStoreReview(Request $request, string $id): RedirectResponse
    {
        if (Auth::user()->user_role_id !== 2) {
            abort(403);
        }

        $validator = Validator::make($request->route()->parameters(), [
            'id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists(Specialist::table(), 'id')->where(function (Builder $query) {
                    if ($this->onlyActive) {
                        $query->where('status', SpecialistStatusEnum::Active);
                    }
                }),
            ],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('catalog.specialists')
                ->withErrors($validator, 'specialist')
                ->withInput();
        }

        $validatedRoute = $validator->validateWithBag('specialist');

        //TODO: проверить был ли ранее отзыв опубликован, чтобы блокировать добавление новых

        $validator = Validator::make($request->post(), [
            'text' => [
                'required',
                'min:3',
                'max:500',
            ],
            'rating' => [
                'required',
                'integer',
                'min:0',
                'max:5',
            ],
            /*'extra_row' => [
                'sometimes',
                'required_with:extra_row.*.title',
                'array:title,value',
            ],*/
            'extra_row.*.title' => [
                'sometimes',
                'required_with:extra_row.*.value',
                //'required_if:extra_row.*.value,required',
                'nullable',
                'distinct:ignore_case',
                'string',
                'min:3',
                'max:100',
            ],
            'extra_row.*.value' => [
                'sometimes',
                'required_with:extra_row.*.title',
                'nullable',
                //'required_if:extra_row.*.title,required',
                'string',
                'min:1',
                'max:100',
            ],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('catalog.specialist.view', ['id' => $id])
                ->withErrors($validator, 'review')
                ->withInput();
        }

        $validated = $validator->validateWithBag('review');

        $specialist = Specialist::where('id', $validatedRoute['id'])->firstOrFail();

        Review::create([
            'text'      => $validated['text'],
            'can_edit'  => ReviewCanEditEnum::Allow,
            'status'    => ReviewStatusEnum::InModeration,
            'rating'    => $validated['rating'],
            'specialist_id'=> $specialist->id,
            'user_id'   => Auth::user()->id,
        ]);

        //TODO: добавить сохранение доп. полей

        return redirect()->back()->with('success', 'Отзыв добавлен. После модерации он появится на странице исполнителя');
    }

    public function specialistEditReview(Request $request, string $id)
    {
        if (Auth::user()->user_role_id !== 2) {
            abort(403);
        }

        $validator = Validator::make($request->post(), [
            'review_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists(Review::table(), 'id')->where(function (Builder $query) {
                    $query->where('user_id', Auth::user()->id);
                    $query->where('can_edit', ReviewCanEditEnum::Allow);
                    $query->where('status', ReviewStatusEnum::Active);
                }),
            ],
            'text' => [
                'required',
                'min:3',
                'max:500',
            ],
            'rating' => [
                'required',
                'integer',
                'min:0',
                'max:5',
            ],
            'extra_row.*.title' => [
                'sometimes',
                'required_with:extra_row.*.value',
                //'required_if:extra_row.*.value,required',
                'nullable',
                'distinct:ignore_case',
                'string',
                'min:3',
                'max:100',
            ],
            'extra_row.*.value' => [
                'sometimes',
                'required_with:extra_row.*.title',
                'nullable',
                //'required_if:extra_row.*.title,required',
                'string',
                'min:1',
                'max:100',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->messages())->setStatusCode(403);
        }

        $validated = $validator->validateWithBag('review');

        $review = Review::where('id', $validated['review_id'])->firstOrFail();

        $review->text = $validated['text'];
        $review->can_edit = ReviewCanEditEnum::Disabled;
        $review->rating = $validated['rating'];
        $review->status = ReviewStatusEnum::InModeration;
        $review->save();

        return response()
        ->json($review)
        ->setStatusCode(200)
        ->header('Content-Type', 'application/json');
    }

    public function companyJobs(Request $request)
    {
        $companyJobs = CompanyJob::with('apiPostUser');

        if ($this->onlyActive) {
            $companyJobs = $companyJobs->where('status', '=', CompanyJobStatusEnum::Active);
        }

        $companyJobs = $companyJobs
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('catalog.companyjobs', [
            'request'           => $request,
            'companyJobs'       => $companyJobs,
        ]);
    }

    public function companyJobView(Request $request, string $id)
    {
        $validated = Validator::make($request->route()->parameters(), [
            'id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists(CompanyJob::table(), 'id')->where(function (Builder $query) {
                    if ($this->onlyActive) {
                        $query->where('status', CompanyJobStatusEnum::Active);
                    }
                }),
            ],
        ])->validated();

        $companyJob = CompanyJob::where('id', $validated['id'])->firstOrFail();

        return view('catalog.companyjob_view', [
            'request'          => $request,
            'companyJob'       => $companyJob,
        ]);
    }
}
