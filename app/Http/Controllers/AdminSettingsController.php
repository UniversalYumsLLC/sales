<?php

namespace App\Http\Controllers;

use App\Services\TestModeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    protected TestModeService $testMode;

    public function __construct(TestModeService $testMode)
    {
        $this->testMode = $testMode;
    }

    /**
     * Display the admin settings page.
     */
    public function index(): Response
    {
        $isLocal = app()->environment('local');

        // Auto-enable test mode in local environment
        if ($isLocal && ! $this->testMode->isEnabled()) {
            $this->testMode->enable();
        }

        return Inertia::render('Admin/Settings', [
            'settings' => [
                'ar_test_mode' => $this->testMode->isEnabled(),
            ],
            'testModeInfo' => [
                'allowedDomain' => $this->testMode->getAllowedDomain(),
                'fulfilEnvironment' => $this->testMode->getFulfilEnvironment(),
            ],
            'environment' => [
                'isLocal' => $isLocal,
                'name' => app()->environment(),
            ],
        ]);
    }

    /**
     * Update settings.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ar_test_mode' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['ar_test_mode'])) {
            if ($validated['ar_test_mode']) {
                $this->testMode->enable();
            } else {
                $this->testMode->disable();
            }
        }

        return response()->json([
            'message' => 'Settings updated successfully',
            'settings' => [
                'ar_test_mode' => $this->testMode->isEnabled(),
            ],
            'testModeInfo' => [
                'fulfilEnvironment' => $this->testMode->getFulfilEnvironment(),
            ],
        ]);
    }
}
