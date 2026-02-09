<?php

namespace App\Http\Controllers;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\CompanyJobStatusEnum;
use App\Enum\ReviewCanEditEnum;
use App\Enum\ReviewStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiPostUser;
use App\Models\BuilderReview;
use App\Models\BuilderReviewCustomField;
use App\Models\CompanyJob;
use App\Models\Review;
use App\Models\ReviewCustomField;
use App\Models\Specialist;
use App\Models\SpecialistSpeciality;
//use Illuminate\Database\Query\Builder;
use App\Models\UserOpenContact;
use App\Services\Tariff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BuilderController extends Controller
{
    protected bool $onlyActive = true;

    protected int $onPage = 10;

    public function __construct(protected Tariff $tariffService)
    {

    }

    public function builders(Request $request)
    {
        $specialitiesList = Repositories::dictionarySpeciality()->getList(ApiDataTypeEnum::Builder, false);

        $validated = $request->validate([
            'key_word' => [
                'sometimes',
                'nullable',
                'string',
                'min:2',
                'max:50',
            ],
            'key_word_tags' => [
                'nullable',
                'array',
            ],
            'key_word_tags.*' => [
                'sometimes',
                'string',
                'min:2',
                'max:50'
            ],
            'speciality_id' => [
                'nullable',
                'array',
            ],
            'speciality_id.*' => [
                'sometimes',
                'integer',
                Rule::in(array_keys($specialitiesList)),
            ],
            'open_contacts' => [
                'sometimes',
                'nullable',
                'integer',
            ],
            'sort' => [
                'nullable',
                'string',
                Rule::in(['builder_reviews_avg_rating', 'last_post_date']),
            ],
            'direction' => [
                'nullable',
                'string',
                Rule::in(['asc', 'desc']),
            ],
            'region' => [
                'nullable',
                'string',
                'max:100',
                Rule::in(array_merge([''], array_keys(config('regions', [])))),
            ],
        ]);

        $authors = ApiPostUser::whereHas('builders', function (Builder $query) use ($validated) {
            $query->whereNotNull('api_channel_post_id')->where('api_channel_post_id', '>', 0);
            if ($this->onlyActive) {
                $query->where('status', '=', ApiPostAiStatusEnum::Active);
            }

            if (!empty($validated['region'])) {
                $query->where('region', $validated['region']);
            }

            if (!empty($validated['key_word_tags'])) {
                $query->whereHas('post', function (Builder $query) use ($validated) {
                    $query->where(function (Builder $query) use ($validated) {
                        foreach ($validated['key_word_tags'] as $keyWordTag) {
                            $query->orWhere('post', 'like', '%'.$keyWordTag.'%');
                        }
                    });
                });
            }

            if (!empty($validated['speciality_id'])) {
                $query->whereRelation('specialities', function (Builder $query) use ($validated) {
                    $query->whereIn('dictionary_speciality_id', $validated['speciality_id']);
                });
            }

            if (!empty($validated['key_word'])) {
                $query->whereHas('post', function (Builder $query) use ($validated) {
                    $query->where('post', 'like', '%'.$validated['key_word'].'%');
                });
            }
        })
            ->whereDoesntHave('specialists');

        if (!empty($validated['open_contacts']) AND Auth::check()) {
            $authors = $authors->whereIn('id', function($query){
                $query->select('api_post_user_id')
                    ->from(with(new UserOpenContact())->getTable())
                    ->where('user_id', Auth::user()->id);
            });
        }

        $authors = $authors->where(function (Builder $query) {
            $query->whereNotNull('phone')
                ->orWhere('username', '<>', '');
        });

        $sortField = $validated['sort'] ?? 'last_post_date';
        $sortDirection = $validated['direction'] ?? 'desc';

        $authors = $authors
            ->with(['builderReviews'])
            ->withAvg(['builderReviews' => function ($query) {
                $query->where('rating', '>', 0);
            }], 'rating')
            ->orderBy($sortField, $sortDirection)
            ->paginate($this->onPage)
            ->withQueryString();

        $userOpenLog = $this->tariffService->getAllContactsByUser(Auth::user());

        return view('catalog.authors_builder', [
            'request' => $request,
            'authors' => $authors,
            'specialitiesList' => $specialitiesList,
            'regionsList' => config('regions', []),
            'tariffAccess' => $this->tariffService->checkOpenContact(),
            'userOpenLog' => $userOpenLog,
        ]);
    }

    public function builderView(Request $request, string $id)
    {
        $validator = Validator::make($request->route()->parameters(), [
            'id' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        if ($validator->fails()) {
            abort(404);
        }

        $validated = $validator->validateWithBag('specialist');

        $author = ApiPostUser::where('id', $validated['id'])
            ->with(['specialists', 'postsComplete', 'specialistReviews']);

        $author = $author->where(function (Builder $query) {
            $query->whereNotNull('phone')
                ->orWhere('username', '<>', '');
        });

        $author = $author->firstOrFail();

        $reviews = $author->builderReviews()->where('status', ReviewStatusEnum::Active)->orderBy('created_at')->get();

        if (!$this->tariffService->checkContactAccess($author)) {
            return view('catalog.buy_tariff', []);
        }
        $this->tariffService->addOpenContactLog(Auth::user(), $author);

        return view('catalog.author_builder_view', [
            'request' => $request,
            'author' => $author,
            'reviews' => $reviews,
        ]);
    }

    public function builderStoreReview(Request $request, string $id): RedirectResponse
    {
        if (Auth::user()->user_role_id !== 2) {
            abort(403);
        }

        $validator = Validator::make($request->route()->parameters(), [
            'id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists(ApiPostUser::table(), 'id'),
            ],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('catalog.builders')
                ->withErrors($validator, 'specialist')
                ->withInput();
        }

        $validatedRoute = $validator->validateWithBag('specialist');

        //TODO: проверить был ли ранее отзыв опубликован, чтобы блокировать добавление новых

        $validator = Validator::make($request->post(), [
            'text' => [
                'required',
                'min:2',
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
            'specialist_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists(\App\Models\Builder::table(), 'id')->where(function (\Illuminate\Database\Query\Builder $query) {
                    if ($this->onlyActive) {
                        $query->where('status', ApiPostAiStatusEnum::Active);
                    }
                }),
            ],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('catalog.builder.view', ['id' => $id])
                ->withErrors($validator, 'review')
                ->withInput();
        }

        $validated = $validator->validateWithBag('review');

        $author = ApiPostUser::where('id', $validatedRoute['id'])->firstOrFail();

        if (isset($validated['specialist_id']) AND $validated['specialist_id']) {
            $specialist = \App\Models\Builder::where('id', $validated['specialist_id'])->firstOrFail();
        }

        $review = BuilderReview::create([
            'text'      => $validated['text'],
            'can_edit'  => ReviewCanEditEnum::Allow,
            'status'    => ReviewStatusEnum::InModeration,
            'rating'    => $validated['rating'],
            'builder_id' => isset($specialist) ? $specialist->id : 0,
            'api_post_user_id' => $author->id,
            'user_id'   => Auth::user()->id,
        ]);

        if (isset($validated['extra_row']) AND count($validated['extra_row'])) {
            foreach ($validated['extra_row'] as $item) {
                if (!$item['title']) {
                    continue;
                }

                BuilderReviewCustomField::create([
                    'review_id' => $review->id,
                    'title' => $item['title'],
                    'value' => $item['value'],
                ]);
            }
        }

        return redirect()->back()->with('success', 'Отзыв добавлен. После модерации он появится на странице исполнителя');
    }

    public function builderEditReview(Request $request, string $id)
    {
        if (Auth::user()->user_role_id !== 2) {
            abort(403);
        }

        $validator = Validator::make($request->post(), [
            'review_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists(BuilderReview::table(), 'id')->where(function (\Illuminate\Database\Query\Builder $query) {
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

        $review = BuilderReview::where('id', $validated['review_id'])->firstOrFail();

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
}
