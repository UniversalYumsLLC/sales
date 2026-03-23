<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    /**
     * Display the email template settings page.
     */
    public function index(): Response
    {
        $user = Auth::user();

        if (! $user->canEditEmailTemplates()) {
            abort(403, 'Email template editing is only available for Accounts Receivable and Admin accounts.');
        }

        $templates = EmailTemplate::all()->keyBy('key');
        $templateTypes = EmailTemplate::getTemplateTypes();

        // Merge template types with stored data
        $mergedTemplates = [];
        foreach ($templateTypes as $key => $typeInfo) {
            $stored = $templates->get($key);
            $mergedTemplates[] = [
                'key' => $key,
                'name' => $typeInfo['name'],
                'description' => $typeInfo['description'],
                'recipient' => $typeInfo['recipient'],
                'placeholders' => $typeInfo['placeholders'],
                'subject' => $stored?->subject ?? '',
                'body' => $stored?->body ?? '',
            ];
        }

        return Inertia::render('Admin/EmailTemplates', [
            'templates' => $mergedTemplates,
        ]);
    }

    /**
     * Update an email template.
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $user = Auth::user();

        if (! $user->canEditEmailTemplates()) {
            abort(403, 'Email template editing is only available for Accounts Receivable and Admin accounts.');
        }

        // Validate the key is valid
        if (! in_array($key, EmailTemplate::getValidKeys())) {
            return response()->json([
                'message' => 'Invalid template key',
            ], 422);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $template = EmailTemplate::updateOrCreate(
            ['key' => $key],
            [
                'name' => EmailTemplate::getTemplateTypes()[$key]['name'],
                'subject' => $validated['subject'],
                'body' => $validated['body'],
            ]
        );

        return response()->json([
            'message' => 'Template updated successfully',
            'template' => [
                'key' => $template->key,
                'name' => $template->name,
                'subject' => $template->subject,
                'body' => $template->body,
            ],
        ]);
    }
}
