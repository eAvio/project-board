<?php

namespace Eavio\ProjectBoard\Http\Controllers;

use Eavio\ProjectBoard\Jobs\ImportTrelloBoardJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportTrelloController extends Controller
{
    /**
     * Handle Trello JSON file upload and dispatch import job.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:json,txt|max:51200', // Max 50MB
            'board_id' => 'nullable|exists:boards,id',
            'boardable_type' => 'nullable|string',
            'boardable_id' => 'nullable|integer',
            'trello_api_key' => 'nullable|string|max:200',
            'trello_api_token' => 'nullable|string|max:500',
        ]);

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid JSON file: ' . json_last_error_msg(),
            ], 422);
        }

        // Validate Trello export structure
        $validation = $this->validateTrelloStructure($data);
        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'error' => $validation['error'],
            ], 422);
        }

        // Store file temporarily for the job to process
        $filename = 'trello-import-' . Str::uuid() . '.json';
        Storage::disk('local')->put("trello-imports/{$filename}", $content);

        // Dispatch the import job
        $job = ImportTrelloBoardJob::dispatch(
            $filename,
            auth()->id(),
            $request->input('board_id'),
            $request->input('boardable_type'),
            $request->input('boardable_id'),
            $request->input('trello_api_key'),
            $request->input('trello_api_token')
        );

        return response()->json([
            'success' => true,
            'message' => 'Import started. You will be notified when complete.',
            'stats' => [
                'board_name' => $data['name'] ?? 'Unknown',
                'lists_count' => count($data['lists'] ?? []),
                'cards_count' => count($data['cards'] ?? []),
                'checklists_count' => count($data['checklists'] ?? []),
                'labels_count' => count($data['labels'] ?? []),
            ],
        ]);
    }

    /**
     * Validate that the uploaded JSON matches Trello export structure.
     */
    private function validateTrelloStructure(array $data): array
    {
        $requiredKeys = ['name', 'lists', 'cards'];
        $missingKeys = [];

        foreach ($requiredKeys as $key) {
            if (!isset($data[$key])) {
                $missingKeys[] = $key;
            }
        }

        if (!empty($missingKeys)) {
            return [
                'valid' => false,
                'error' => 'Invalid Trello export: missing required keys: ' . implode(', ', $missingKeys),
            ];
        }

        // Check that lists and cards are arrays
        if (!is_array($data['lists'])) {
            return [
                'valid' => false,
                'error' => 'Invalid Trello export: "lists" must be an array',
            ];
        }

        if (!is_array($data['cards'])) {
            return [
                'valid' => false,
                'error' => 'Invalid Trello export: "cards" must be an array',
            ];
        }

        // Validate list structure
        foreach ($data['lists'] as $index => $list) {
            if (!isset($list['id']) || !isset($list['name'])) {
                return [
                    'valid' => false,
                    'error' => "Invalid list at index {$index}: missing 'id' or 'name'",
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Get import status/progress (optional endpoint for polling).
     */
    public function status(Request $request): JsonResponse
    {
        // This could check a cache key or database record for progress
        // For now, Nova's built-in job tracking handles this
        return response()->json([
            'status' => 'Processing imports are tracked via Nova notifications',
        ]);
    }
}
