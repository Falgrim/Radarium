<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\MoonShine\Pages\ParsingAccuracyTestPage;
use App\Services\ParsingAccuracyTestService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

final class ParsingAccuracyTestController extends \App\Http\Controllers\Controller
{
    public function start(ParsingAccuracyTestService $service): RedirectResponse
    {
        $pageUrl = moonshineRouter()->to_page(ParsingAccuracyTestPage::make());

        try {
            $service->start();
        } catch (RuntimeException $e) {
            return redirect()->to($pageUrl)->with('parsing_test_error', $e->getMessage());
        }

        return redirect()->to($pageUrl);
    }
}
