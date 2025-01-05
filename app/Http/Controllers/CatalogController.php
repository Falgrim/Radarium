<?php

namespace App\Http\Controllers;

use App\Enum\SpecialistStatusEnum;
use App\Models\ApiPostUser;
use App\Models\Specialist;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index()
    {
        /*$specialists = DB::table(Specialist::table())
            //->where('status', '=', SpecialistStatusEnum::Active)
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(5);*/

        $specialists = Specialist::with('user')
            //->where('status', '=', SpecialistStatusEnum::Active)
            ->orderByDesc('created_at')
            ->paginate(5);

        return view('catalog.index', [
            'specialists' => $specialists,
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
                    //$query->where('status', SpecialistStatusEnum::Active);
                }),
            ],
        ])->validated();

        $specialist = Specialist::where('id', $validated['id'])->firstOrFail();
        dd($validated['id'], $specialist);
    }
}
