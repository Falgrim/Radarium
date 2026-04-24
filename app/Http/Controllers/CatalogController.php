<?php

namespace App\Http\Controllers;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\CompanyJobStatusEnum;
use App\Enum\ReviewCanEditEnum;
use App\Enum\ReviewStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\CompanyJob;
use App\Models\Review;
use App\Models\ReviewCustomField;
use App\Models\Specialist;
use App\Models\SpecialistSpeciality;
use App\Models\UserOpenContact;
use App\Services\Tariff;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    protected bool $onlyActive = true;

    protected int $onPage = 10;

    public function __construct(protected Tariff $tariffService)
    {

    }

    /**
     * Одна лёгкая выборка последних постов для текущей страницы пагинации.
     * Eloquent latestOfMany() при eager load даёт тяжёлый SQL на больших таблицах.
     */
    private function attachLatestCompletePostsToAuthorsPaginator(LengthAwarePaginator $authors): void
    {
        $rows = $authors->getCollection();
        if ($rows->isEmpty()) {
            return;
        }

        $userIds = $rows->pluck('id')->all();
        $complete = ApiChannelPostStatusEnum::Complete->value;
        $postsTable = (new ApiChannelPost())->getTable();

        $maxDatePerUser = DB::table($postsTable)
            ->select('api_post_user_id', DB::raw('MAX(post_date) as max_post_date'))
            ->where('ai_parse_status', $complete)
            ->whereIn('api_post_user_id', $userIds)
            ->groupBy('api_post_user_id');

        $postIdByUser = DB::query()
            ->from($postsTable.' as p')
            ->joinSub($maxDatePerUser, 'mx', function ($join) {
                $join->on('p.api_post_user_id', '=', 'mx.api_post_user_id')
                    ->on('p.post_date', '=', 'mx.max_post_date');
            })
            ->where('p.ai_parse_status', $complete)
            ->groupBy('p.api_post_user_id')
            ->select('p.api_post_user_id', DB::raw('MAX(p.id) as post_id'))
            ->pluck('post_id', 'api_post_user_id');

        if ($postIdByUser->isEmpty()) {
            foreach ($rows as $author) {
                $author->setRelation('latestCompletePost', null);
            }

            return;
        }

        $posts = ApiChannelPost::query()
            ->whereIn('id', $postIdByUser->values())
            ->get()
            ->keyBy('api_post_user_id');

        foreach ($rows as $author) {
            $author->setRelation('latestCompletePost', $posts->get($author->id));
        }
    }

    /**
     * Регионы для выпадающего списка каталога проектировщиков: только значения, по которым есть записи
     * при текущих фильтрах (без учёта выбранного региона).
     *
     * @param  array<string, mixed>  $validated
     * @return array{0: array<string, string>, 1: bool}
     */
    private function specialistCatalogRegionOptions(array $validated): array
    {
        $base = $this->specialistsMatchingCatalogScope($validated);

        $hasNullRegion = (clone $base)
            ->where(function (Builder $q) {
                $q->whereNull('region')->orWhere('region', '');
            })
            ->exists();

        $names = (clone $base)
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->distinct()
            ->orderBy('region')
            ->pluck('region')
            ->all();

        $regionsList = [];
        foreach ($names as $name) {
            $regionsList[$name] = $name;
        }

        return [$regionsList, $hasNullRegion];
    }

    /**
     * Специалисты, попадающие в каталог при тех же условиях поиска, что и основной список, без фильтра по region.
     *
     * @param  array<string, mixed>  $validated
     * @return Builder<\App\Models\Specialist>
     */
    private function specialistsMatchingCatalogScope(array $validated): Builder
    {
        return Specialist::query()
            ->when($this->onlyActive, function (Builder $q) {
                $q->where('status', '=', ApiPostAiStatusEnum::Active);
            })
            ->when(! empty($validated['key_word_tags']), function (Builder $query) use ($validated) {
                $query->whereHas('post', function (Builder $postQuery) use ($validated) {
                    $postQuery->where(function (Builder $inner) use ($validated) {
                        foreach ($validated['key_word_tags'] as $keyWordTag) {
                            $inner->orWhere('post', 'like', '%'.$keyWordTag.'%');
                        }
                    });
                });
            })
            ->when(! empty($validated['speciality_id']), function (Builder $query) use ($validated) {
                $query->whereRelation('specialities', function (Builder $relationQuery) use ($validated) {
                    $relationQuery->whereIn('dictionary_speciality_id', $validated['speciality_id']);
                });
            })
            ->whereHas('user', function (Builder $userQuery) use ($validated) {
                $userQuery->whereHas('postsComplete', function (Builder $postsQuery) use ($validated) {
                    if (! empty($validated['key_word'])) {
                        $postsQuery->where('post', 'like', '%'.$validated['key_word'].'%');
                    }
                });
                if (! empty($validated['open_contacts']) && Auth::check()) {
                    $userQuery->whereIn('id', function ($sub) {
                        $sub->select('api_post_user_id')
                            ->from(with(new UserOpenContact())->getTable())
                            ->where('user_id', Auth::user()->id);
                    });
                }
                $userQuery->where(function (Builder $contact) {
                    $contact->whereNotNull('phone')
                        ->orWhere('username', '<>', '');
                });
            });
    }

    public function authorsAsSpecialists(Request $request)
    {
        $specialitiesList = Cache::remember(
            'catalog.specialists.specialities_list_v1',
            3600,
            static fn () => Repositories::dictionarySpeciality()->getList(ApiDataTypeEnum::Specialist, false)
        );

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
                Rule::in(['specialist_reviews_avg_rating', 'last_post_date']),
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
            ],
        ]);

        [$regionsList, $hasNullRegion] = $this->specialistCatalogRegionOptions($validated);

        $allowedRegions = array_merge([''], array_keys($regionsList));
        if ($hasNullRegion) {
            $allowedRegions[] = 'none';
            $allowedRegions = array_values(array_unique($allowedRegions));
        }

        Validator::make($request->all(), [
            'region' => ['nullable', 'string', 'max:100', Rule::in($allowedRegions)],
        ])->validate();

        $authors = ApiPostUser::whereHas('specialists', function (Builder $query) use ($validated) {
            if ($this->onlyActive) {
                $query->where('status', '=', ApiPostAiStatusEnum::Active);
            }

            if (!empty($validated['region'])) {
                if ($validated['region'] === 'none') {
                    $query->whereNull('region');
                } else {
                    $query->where('region', $validated['region']);
                }
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
        })->whereHas('postsComplete', function (Builder $query) use ($validated) {
            if (!empty($validated['key_word'])) {
                $query->where('post', 'like', '%'.$validated['key_word'].'%');
            }
        });

        if (!empty($validated['open_contacts']) && Auth::check()) {
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
            ->with([
                'specialists' => static function ($query): void {
                    $query->where('status', ApiPostAiStatusEnum::Active)
                        ->with(['specialities.dictionarySpeciality']);
                },
            ])
            ->withAvg(['specialistReviews' => function ($query) {
                $query->where('rating', '>', 0);
            }], 'rating')
            ->orderBy($sortField, $sortDirection)
            ->paginate($this->onPage)
            ->withQueryString();

        $this->attachLatestCompletePostsToAuthorsPaginator($authors);

        $userOpenLog = $this->tariffService->getAllContactsByUser(Auth::user());

        return view('catalog.authors', [
            'request' => $request,
            'authors' => $authors,
            'specialitiesList' => $specialitiesList,
            'regionsList' => $regionsList,
            'showRegionNoneFilterOption' => $hasNullRegion,
            'tariffAccess' => $this->tariffService->checkOpenContact(),
            'userOpenLog' => $userOpenLog,
        ]);
    }

    public function specialists(Request $request)
    {
        $specialitiesList = Repositories::dictionarySpeciality()->getList(ApiDataTypeEnum::Specialist, false);

        $validated = $request->validate([
            'key_word' => [
                'sometimes',
                'nullable',
                'string',
                'min:2',
                'max:50',
            ],
            'speciality_id' => [
                'sometimes',
                'nullable',
                'string',
                'alpha_dash:ascii',
                Rule::in(array_keys($specialitiesList)),
            ],
        ]);

        $specialists = Specialist::with('user');

        if ($this->onlyActive) {
            $specialists = $specialists->where('status', '=', ApiPostAiStatusEnum::Active);
        }

        if (!empty($validated['key_word'])) {
            $specialists = $specialists->whereHas('post', function (Builder $query) use ($validated) {
                $query->where('post', 'like', '%'.$validated['key_word'].'%');
            });
        }

        if (!empty($validated['speciality_id'])) {
            $specialists = $specialists->whereRelation('specialities', 'dictionary_speciality_id', $validated['speciality_id']);
        }

        $specialists = $specialists
            ->with('reviews')
            ->orderByDesc('created_at')
            ->paginate($this->onPage)
            ->withQueryString();

        return view('catalog.specialists', [
            'request'           => $request,
            'specialists'       => $specialists,
            'specialitiesList'    => $specialitiesList,
            'tariffAccess' => $this->tariffService->checkContactAccess(),
        ]);
    }

    public function authorAsSpecialistView(Request $request, string $id)
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
            ->with(['specialists', 'postsComplete', 'specialistReviews'])
            ->withAvg(['specialistReviews' => function ($query) {
                $query->where('rating', '>', 0);
            }], 'rating');

        $author = $author->where(function (Builder $query) {
            $query->whereNotNull('phone')
                ->orWhere('username', '<>', '');
        });

        $author = $author->firstOrFail();

        $reviews = $author->specialistReviews()->where('status', ReviewStatusEnum::Active)->orderBy('created_at')->get();

        if (!$this->tariffService->checkContactAccess($author)) {
            return view('catalog.buy_tariff', []);
        }
        $this->tariffService->addOpenContactLog(Auth::user(), $author);

        return view('catalog.author_view', [
            'request' => $request,
            'author' => $author,
            'reviews' => $reviews,
        ]);
    }

    public function specialistView(Request $request, string $id)
    {
        $validator = Validator::make($request->route()->parameters(), [
            'id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists(Specialist::table(), 'id')->where(function (\Illuminate\Database\Query\Builder $query) {
                    if ($this->onlyActive) {
                        $query->where('status', ApiPostAiStatusEnum::Active);
                    }
                }),
            ],
        ]);

        if ($validator->fails()) {
            abort(404);
        }

        $validated = $validator->validateWithBag('specialist');

        $specialist = Specialist::where('id', $validated['id'])->with('post')->firstOrFail();
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
                Rule::exists(ApiPostUser::table(), 'id'),
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
                Rule::exists(Specialist::table(), 'id')->where(function (\Illuminate\Database\Query\Builder $query) {
                    if ($this->onlyActive) {
                        $query->where('status', ApiPostAiStatusEnum::Active);
                    }
                }),
            ],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('catalog.specialist.view', ['id' => $id])
                ->withErrors($validator, 'review')
                ->withInput();
        }

        $validated = $validator->validateWithBag('review');

        $author = ApiPostUser::where('id', $validatedRoute['id'])->firstOrFail();

        if (isset($validated['specialist_id']) && $validated['specialist_id']) {
            $specialist = Specialist::where('id', $validated['specialist_id'])->firstOrFail();
        }

        $check = Review::where('specialist_id', isset($specialist) ? $specialist->id : 0)
            ->where('api_post_user_id', $author->id)
            ->where('user_id', Auth::user()->id)
            ->first();

        try {
            if ($check) {
                throw new \Exception('Вы уже ранее оставляли отзыв для данного специалиста. Если его нет, то, возможно, он еще проходит модерацию.');
            }
        } catch (\Exception $e) {
            $validator->errors()->add('review_custome', $e->getMessage());
            return redirect()->back()->withErrors($validator, 'review')->withInput();
        }

        $review = Review::create([
            'text'      => $validated['text'],
            'can_edit'  => ReviewCanEditEnum::Allow,
            'status'    => ReviewStatusEnum::InModeration,
            'rating'    => $validated['rating'],
            'specialist_id' => isset($specialist) ? $specialist->id : 0,
            'api_post_user_id' => $author->id,
            'user_id'   => Auth::user()->id,
        ]);

        if (isset($validated['extra_row']) && count($validated['extra_row'])) {
            foreach ($validated['extra_row'] as $item) {
                if (!$item['title']) {
                    continue;
                }

                ReviewCustomField::create([
                    'review_id' => $review->id,
                    'title' => $item['title'],
                    'value' => $item['value'],
                ]);
            }
        }

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
                Rule::exists(Review::table(), 'id')->where(function (\Illuminate\Database\Query\Builder $query) {
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
            ->paginate($this->onPage)
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
                Rule::exists(CompanyJob::table(), 'id')->where(function (\Illuminate\Database\Query\Builder $query) {
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
