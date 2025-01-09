<?php

namespace App\Http\Controllers;

use App\Enum\CompanyJobStatusEnum;
use App\Enum\SpecialistStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiPostUser;
use App\Models\CompanyJob;
use App\Models\Specialist;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
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
            ->paginate(5)
            ->withQueryString();

        return view('catalog.specialists', [
            'request'           => $request,
            'specialists'       => $specialists,
            'experienceList'    => $experienceList,
        ]);
    }

    public function specialistView(Request $request, string $id)
    {
        $validated = Validator::make($request->route()->parameters(), [
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
        ])->validated();

        $specialist = Specialist::where('id', $validated['id'])->firstOrFail();

        return view('catalog.specialist_view', [
            'request'          => $request,
            'specialist'       => $specialist,
        ]);
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
