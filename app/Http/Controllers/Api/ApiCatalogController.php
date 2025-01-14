<?php

namespace App\Http\Controllers\Api;

use App\Enum\CompanyJobStatusEnum;
use App\Enum\SpecialistStatusEnum;
use App\Http\Controllers\Controller;
use App\Infrastructures\Facades\Repositories;
use App\Models\CompanyJob;
use App\Models\Specialist;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ApiCatalogController extends Controller
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
            ->paginate(5)
            ->withQueryString();

        return $specialists;
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
        //$reviews = $specialist->reviews()->orderBy('created_at')->get();

        return $specialist;
    }

    public function companyJobs(Request $request)
    {
        $companyJobs = CompanyJob::with('apiPostUser');

        if ($this->onlyActive) {
            $companyJobs = $companyJobs->where('status', '=', CompanyJobStatusEnum::Active);
        }

        $companyJobs = $companyJobs
            ->orderByDesc('created_at')
            ->paginate(5)
            ->withQueryString();

        return $companyJobs;
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

        return $companyJob;
    }
}
