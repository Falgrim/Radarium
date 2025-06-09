<?php

namespace App\View\Components;

use App\Infrastructures\Facades\Repositories;
use Illuminate\View\Component;
use Illuminate\View\View;

class LandingLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $userRoleList = Repositories::userRole()->getList();

        return view('layouts.landing', [
            'userRoleList' => $userRoleList,
        ]);
    }
}
