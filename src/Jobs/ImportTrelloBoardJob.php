<?php

namespace Eavio\ProjectBoard\Jobs;

use Eavio\ProjectBoard\Models\Activity;
use Eavio\ProjectBoard\Models\Board;
use Eavio\ProjectBoard\Models\BoardColumn;
use Eavio\ProjectBoard\Models\Card;
use Eavio\ProjectBoard\Models\Checklist;
use Eavio\ProjectBoard\Models\ChecklistItem;
use Eavio\ProjectBoard\Models\Label;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportTrelloBoardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour max
    public int $tries = 1;

    protected string $filename;
    protected int $userId;
    protected ?int $existingBoardId;
    protected ?string $boardableType;
    protected ?int $boardableId;
    protected ?string $trelloApiKey;
    protected ?string $trelloApiToken;

    // Mapping arrays for Trello IDs to local IDs
    protected array $listMapping = [];
    protected array $labelMapping = [];
    protected array $cardMapping = [];

    // Import statistics
    protected array $stats = [
        'lists_created' => 0,
        'cards_created' => 0,
        'labels_created' => 0,
        'checklists_created' => 0,
        'checklist_items_created' => 0,
        'comments_created' => 0,
        'attachments_downloaded' => 0,
        'errors' => [],
    ];

    public function __construct(
        string $filename,
        int $userId,
        ?int $existingBoardId = null,
        ?string $boardableType = null,
        ?int $boardableId = null,
        ?string $trelloApiKey = null,
        ?string $trelloApiToken = null
    ) {
        $this->filename = $filename;
        $this->userId = $userId;
        $this->existingBoardId = $existingBoardId;
        $this->boardableType = $boardableType;
        $this->boardableId = $boardableId;
        $this->trelloApiKey = $trelloApiKey;
        $this->trelloApiToken = $trelloApiToken;
        
        // DEBUG: Log if credentials are provided
        Log::info("[TrelloImport] Job constructor - API Key: " . ($this->trelloApiKey ? 'YES' : 'NO'));
        Log::info("[TrelloImport] Job constructor - API Token: " . ($this->trelloApiToken ? 'YES' : 'NO'));
    }

    public function handle(): void
    {
        Log::info("[TrelloImport] Starting import job for file: {$this->filename}");

        try {
            $content = Storage::disk('local')->get("trello-imports/{$this->filename}");
            $data = json_decode($content, true);

            if (!$data) {
                throw new \Exception('Failed to parse Trello JSON file');
            }

            DB::beginTransaction();

            // 1. Create or get the board
            $board = $this->createBoard($data);
            Log::info("[TrelloImport] Board created/updated: {$board->id} - {$board->name}");

            // 2. Import labels (board-level in Trello)
            $this->importLabels($data['labels'] ?? []);
            Log::info("[TrelloImport] Labels imported: {$this->stats['labels_created']}");

            // 3. Import lists (columns)
            $this->importLists($board, $data['lists'] ?? []);
            Log::info("[TrelloImport] Lists imported: {$this->stats['lists_created']}");

            // 4. Import cards
            $this->importCards($data['cards'] ?? []);
            Log::info("[TrelloImport] Cards imported: {$this->stats['cards_created']}");

            // 5. Import checklists
            $this->importChecklists($data['checklists'] ?? []);
            Log::info("[TrelloImport] Checklists imported: {$this->stats['checklists_created']}");

            // 6. Import comments from actions
            $this->importComments($data['actions'] ?? []);
            Log::info("[TrelloImport] Comments imported: {$this->stats['comments_created']}");

            DB::commit();

            // Clean up temporary file
            Storage::disk('local')->delete("trello-imports/{$this->filename}");

            // Send success notification
            $this->sendNotification($board, true);

            Log::info("[TrelloImport] Import completed successfully", $this->stats);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[TrelloImport] Import failed: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            $this->stats['errors'][] = $e->getMessage();
            $this->sendNotification(null, false, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Create or get the target board.
     */
    protected function createBoard(array $data): Board
    {
        if ($this->existingBoardId) {
            return Board::findOrFail($this->existingBoardId);
        }

        $boardData = [
            'name' => $data['name'] ?? 'Imported Board',
        ];

        // Handle board description if present
        if (!empty($data['desc'])) {
            $boardData['description'] = $data['desc'];
        }

        // Handle boardable (polymorphic relation)
        if ($this->boardableType && $this->boardableId) {
            $boardData['boardable_type'] = $this->boardableType;
            $boardData['boardable_id'] = $this->boardableId;
        }

        return Board::create($boardData);
    }

    /**
     * Import Trello labels.
     */
    protected function importLabels(array $trelloLabels): void
    {
        foreach ($trelloLabels as $trelloLabel) {
            try {
                // Skip labels without name
                $name = $trelloLabel['name'] ?? '';
                if (empty($name)) {
                    $name = ucfirst($trelloLabel['color'] ?? 'Unnamed');
                }

                // Map Trello colors to hex codes
                $color = $this->mapTrelloColor($trelloLabel['color'] ?? 'gray');

                // Check if label already exists
                $existingLabel = Label::where('name', $name)
                    ->where('color', $color)
                    ->first();

                if ($existingLabel) {
                    $this->labelMapping[$trelloLabel['id']] = $existingLabel->id;
                } else {
                    $label = Label::create([
                        'name' => $name,
                        'color' => $color,
                    ]);
                    $this->labelMapping[$trelloLabel['id']] = $label->id;
                    $this->stats['labels_created']++;
                }
            } catch (\Exception $e) {
                $this->stats['errors'][] = "Label import failed: " . $e->getMessage();
                Log::warning("[TrelloImport] Label import error", [
                    'label' => $trelloLabel,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Import Trello lists as BoardColumns.
     */
    protected function importLists(Board $board, array $trelloLists): void
    {
        // Sort lists by position
        usort($trelloLists, fn($a, $b) => ($a['pos'] ?? 0) <=> ($b['pos'] ?? 0));

        $order = 0;
        foreach ($trelloLists as $trelloList) {
            try {
                // Skip closed/archived lists optionally (import them but mark closed)
                $column = BoardColumn::create([
                    'board_id' => $board->id,
                    'name' => $trelloList['name'],
                    'slug' => Str::slug($trelloList['name']),
                    'order_column' => $order++,
                ]);

                $this->listMapping[$trelloList['id']] = $column->id;
                $this->stats['lists_created']++;
            } catch (\Exception $e) {
                $this->stats['errors'][] = "List import failed ({$trelloList['name']}): " . $e->getMessage();
                Log::warning("[TrelloImport] List import error", [
                    'list' => $trelloList,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Import Trello cards.
     */
    protected function importCards(array $trelloCards): void
    {
        // Sort cards by position within each list
        usort($trelloCards, fn($a, $b) => ($a['pos'] ?? 0) <=> ($b['pos'] ?? 0));

        // Group cards by list for proper ordering
        $cardsByList = [];
        foreach ($trelloCards as $card) {
            $listId = $card['idList'];
            if (!isset($cardsByList[$listId])) {
                $cardsByList[$listId] = [];
            }
            $cardsByList[$listId][] = $card;
        }

        foreach ($cardsByList as $trelloListId => $cards) {
            $columnId = $this->listMapping[$trelloListId] ?? null;
            if (!$columnId) {
                Log::warning("[TrelloImport] Skipping cards for unknown list: {$trelloListId}");
                continue;
            }

            $order = 0;
            foreach ($cards as $trelloCard) {
                try {
                    $this->importCard($trelloCard, $columnId, $order++);
                } catch (\Exception $e) {
                    $this->stats['errors'][] = "Card import failed ({$trelloCard['name']}): " . $e->getMessage();
                    Log::warning("[TrelloImport] Card import error", [
                        'card' => $trelloCard['name'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Import a single Trello card.
     */
    protected function importCard(array $trelloCard, int $columnId, int $order): void
    {
        // Parse due date
        $dueDate = null;
        if (!empty($trelloCard['due'])) {
            try {
                $dueDate = \Carbon\Carbon::parse($trelloCard['due'])->toDateString();
            } catch (\Exception $e) {
                // Ignore invalid dates
            }
        }

        // Prepare title and description, respecting database limits
        $title = $trelloCard['name'] ?? 'Untitled card';
        $description = $trelloCard['desc'] ?? null;

        if (mb_strlen($title) > 255) {
            $originalTitle = $title;
            $title = mb_substr($title, 0, 252) . '...';

            $note = "Original Trello title (truncated in card title):\n" . $originalTitle;

            if ($description) {
                $description = $description . "\n\n" . $note;
            } else {
                $description = $note;
            }
        }

        // Create the card
        $card = Card::create([
            'board_column_id' => $columnId,
            'title' => $title,
            'description' => $description,
            'order_column' => $order,
            'due_date' => $dueDate,
            'created_by' => $this->userId,
        ]);

        // Handle archived/closed cards
        if ($trelloCard['closed'] ?? false) {
            $card->delete(); // Soft delete to archive
        }

        // Handle completed status
        if ($trelloCard['dueComplete'] ?? false) {
            $card->update(['completed_at' => now()]);
        }

        $this->cardMapping[$trelloCard['id']] = $card->id;
        $this->stats['cards_created']++;

        // Attach labels
        $this->attachLabels($card, $trelloCard['idLabels'] ?? []);

        // Import attachments
        $this->importAttachments($card, $trelloCard['attachments'] ?? []);

        // Handle cover image
        if (!empty($trelloCard['cover']['idAttachment'])) {
            $this->setCoverFromAttachment($card, $trelloCard);
        }
    }

    /**
     * Attach labels to a card.
     */
    protected function attachLabels(Card $card, array $trelloLabelIds): void
    {
        $labelIds = [];
        foreach ($trelloLabelIds as $trelloLabelId) {
            if (isset($this->labelMapping[$trelloLabelId])) {
                $labelIds[] = $this->labelMapping[$trelloLabelId];
            }
        }

        if (!empty($labelIds)) {
            $card->labels()->attach($labelIds);
        }
    }

    /**
     * Import attachments for a card.
     */
    protected function importAttachments(Card $card, array $attachments): void
    {
        foreach ($attachments as $attachment) {
            try {
                $url = $attachment['url'] ?? null;
                if (!$url) {
                    continue;
                }

                // Check if it's a Trello-hosted attachment
                if (str_contains($url, 'trello.com') || str_contains($url, 'trello-attachments')) {
                    $this->downloadTrelloAttachment($card, $attachment);
                } else {
                    // External URL - try to add directly
                    $card->addMediaFromUrl($url)
                        ->usingFileName($attachment['fileName'] ?? $attachment['name'] ?? 'attachment')
                        ->toMediaCollection('attachments');
                    $this->stats['attachments_downloaded']++;
                }
            } catch (\Exception $e) {
                $this->stats['errors'][] = "Attachment failed ({$attachment['name']}): " . $e->getMessage();
                Log::warning("[TrelloImport] Attachment download failed", [
                    'attachment' => $attachment['name'] ?? 'unknown',
                    'url' => $attachment['url'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Download a Trello-hosted attachment.
     */
    protected function downloadTrelloAttachment(Card $card, array $attachment, string $collection = 'attachments'): void
    {
        $url = $attachment['url'] ?? null;
        $fileName = $attachment['fileName'] ?? $attachment['name'] ?? 'attachment';

        // DEBUG: Log initial state
        Log::info("[TrelloImport] Download attempt for: {$fileName}");
        Log::info("[TrelloImport] Original URL: " . ($url ?: 'NULL'));
        Log::info("[TrelloImport] Has API Key: " . ($this->trelloApiKey ? 'YES' : 'NO'));
        Log::info("[TrelloImport] Has API Token: " . ($this->trelloApiToken ? 'YES' : 'NO'));

        // If we have Trello API credentials, try to fetch attachment metadata via the official REST endpoint
        if ($this->trelloApiKey && $this->trelloApiToken && $url) {
            try {
                $parts = parse_url($url);
                $path = $parts['path'] ?? '';

                Log::info("[TrelloImport] Parsed path: " . ($path ?: 'EMPTY'));

                $apiPath = null;

                // Typical Trello download URL: /1/cards/{cardId}/attachments/{attachmentId}/download/{fileName}
                if ($path && preg_match('#^/1/cards/([^/]+)/attachments/([^/]+)/#', $path, $matches)) {
                    $cardId = $matches[1] ?? null;
                    $attachmentId = $matches[2] ?? null;

                    if ($cardId && $attachmentId) {
                        $apiPath = "/1/cards/{$cardId}/attachments/{$attachmentId}";
                        Log::info("[TrelloImport] Using API path: {$apiPath}");
                    }
                }

                if ($apiPath) {
                    $baseUrl = 'https://api.trello.com' . $apiPath;

                    $query = [];
                    if (!empty($parts['query'])) {
                        parse_str($parts['query'], $query);
                    }

                    $query['key'] = $this->trelloApiKey;
                    $query['token'] = $this->trelloApiToken;

                    $authUrl = $baseUrl . '?' . http_build_query($query);

                    Log::info("[TrelloImport] Metadata URL being called: {$authUrl}");

                    $response = Http::timeout(60)->get($authUrl);

                    Log::info("[TrelloImport] Metadata response status: {$response->status()}");
                    Log::info("[TrelloImport] Metadata response body (first 500 chars): " . substr($response->body(), 0, 500));

                    if ($response->successful()) {
                        $data = json_decode($response->body(), true);

                        if (json_last_error() !== JSON_ERROR_NONE) {
                            Log::warning("[TrelloImport] Failed to decode attachment metadata JSON", [
                                'file' => $fileName,
                                'error' => json_last_error_msg(),
                            ]);
                        } else {
                            // Attachment metadata may be an object or array; normalize
                            $meta = $data;
                            if (isset($data[0]) && is_array($data[0]) && !isset($data['url'])) {
                                $meta = $data[0];
                            }

                            $downloadUrl = $meta['url'] ?? null;

                            Log::info("[TrelloImport] Metadata URL field: " . ($downloadUrl ?: 'NULL'));

                            if ($downloadUrl) {
                                try {
                                    // The download URL also requires authentication - append key/token
                                    $downloadUrlParts = parse_url($downloadUrl);
                                    $downloadQuery = [];
                                    if (!empty($downloadUrlParts['query'])) {
                                        parse_str($downloadUrlParts['query'], $downloadQuery);
                                    }
                                    $downloadQuery['key'] = $this->trelloApiKey;
                                    $downloadQuery['token'] = $this->trelloApiToken;
                                    
                                    $authenticatedDownloadUrl = ($downloadUrlParts['scheme'] ?? 'https') . '://' 
                                        . ($downloadUrlParts['host'] ?? 'trello.com') 
                                        . ($downloadUrlParts['path'] ?? '') 
                                        . '?' . http_build_query($downloadQuery);
                                    
                                    Log::info("[TrelloImport] Downloading with auth from: " . substr($authenticatedDownloadUrl, 0, 100) . '...');
                                    
                                    // Download file content with authentication
                                    $fileResponse = Http::timeout(120)->get($authenticatedDownloadUrl);
                                    
                                    if ($fileResponse->successful()) {
                                        $fileContent = $fileResponse->body();
                                        $tempPath = sys_get_temp_dir() . '/' . uniqid('trello_') . '_' . $fileName;
                                        
                                        file_put_contents($tempPath, $fileContent);
                                        
                                        Log::info("[TrelloImport] Downloaded {$fileName} to temp: {$tempPath} (" . strlen($fileContent) . " bytes)");
                                        
                                        $card->addMedia($tempPath)
                                            ->usingFileName($fileName)
                                            ->toMediaCollection($collection);
                                        
                                        // Clean up temp file (MediaLibrary moves it, but just in case)
                                        if (file_exists($tempPath)) {
                                            @unlink($tempPath);
                                        }

                                        $this->stats['attachments_downloaded']++;
                                        Log::info("[TrelloImport] Successfully imported Trello attachment: {$fileName}");
                                        return;
                                    } else {
                                        Log::warning("[TrelloImport] Authenticated download failed", [
                                            'file' => $fileName,
                                            'status' => $fileResponse->status(),
                                            'body' => substr($fileResponse->body(), 0, 200),
                                        ]);
                                    }
                                } catch (\Exception $e) {
                                    Log::warning("[TrelloImport] Download from metadata URL failed", [
                                        'file' => $fileName,
                                        'url' => $downloadUrl,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            } else {
                                Log::warning("[TrelloImport] Attachment metadata missing 'url' field", [
                                    'file' => $fileName,
                                    'meta' => $meta,
                                ]);
                            }
                        }
                    } else {
                        Log::warning("[TrelloImport] Non-successful metadata response for {$fileName}", [
                            'status' => $response->status(),
                            'body' => substr($response->body(), 0, 200),
                        ]);
                    }
                } else {
                    Log::warning("[TrelloImport] Could not parse Trello attachment path to API path", [
                        'file' => $fileName,
                        'path' => $path,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning("[TrelloImport] Failed to fetch Trello attachment metadata with key/token", [
                    'file' => $fileName,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } else {
            Log::info("[TrelloImport] Skipping API attempt - missing credentials or URL");
        }

        // Fallback: try without auth (works for some public attachments)
        try {
            if ($url) {
                Log::info("[TrelloImport] Trying fallback without auth for: {$fileName}");
                $card->addMediaFromUrl($url)
                    ->usingFileName($fileName)
                    ->toMediaCollection($collection);
                $this->stats['attachments_downloaded']++;
                Log::info("[TrelloImport] Fallback succeeded for: {$fileName}");
                return;
            }
        } catch (\Exception $e) {
            // Log the skipped attachment
            Log::info("[TrelloImport] Skipping Trello attachment (auth required): {$fileName}");
            Log::warning("[TrelloImport] Fallback failed", [
                'file' => $fileName,
                'error' => $e->getMessage(),
            ]);

            Activity::create([
                'card_id' => $card->id,
                'user_id' => $this->userId,
                'type' => 'imported',
                'text' => "Note: Attachment '{$fileName}' was not imported (requires Trello API key + token)",
            ]);
        }
    }

    /**
     * Set cover image from attachment.
     */
    protected function setCoverFromAttachment(Card $card, array $trelloCard): void
    {
        // The cover is typically already in attachments, just need to set it as featured
        $coverId = $trelloCard['cover']['idAttachment'] ?? null;
        if (!$coverId) return;

        // Find the attachment in the card's attachments
        foreach ($trelloCard['attachments'] ?? [] as $att) {
            if ($att['id'] === $coverId && !empty($att['url'])) {
                try {
                    // Check if it's an image
                    $mimeType = $att['mimeType'] ?? '';
                    if (str_starts_with($mimeType, 'image/')) {
                        $this->downloadTrelloAttachment($card, $att, 'featured_image');
                    }
                } catch (\Exception $e) {
                    Log::warning("[TrelloImport] Cover image failed", ['error' => $e->getMessage()]);
                }
                break;
            }
        }
    }

    /**
     * Import Trello checklists.
     */
    protected function importChecklists(array $trelloChecklists): void
    {
        foreach ($trelloChecklists as $trelloChecklist) {
            try {
                $cardId = $this->cardMapping[$trelloChecklist['idCard']] ?? null;
                if (!$cardId) {
                    Log::warning("[TrelloImport] Skipping checklist for unknown card: {$trelloChecklist['idCard']}");
                    continue;
                }

                $checklist = Checklist::create([
                    'card_id' => $cardId,
                    'name' => $trelloChecklist['name'],
                ]);

                $this->stats['checklists_created']++;

                // Import checklist items
                $this->importChecklistItems($checklist, $trelloChecklist['checkItems'] ?? []);

            } catch (\Exception $e) {
                $this->stats['errors'][] = "Checklist import failed ({$trelloChecklist['name']}): " . $e->getMessage();
                Log::warning("[TrelloImport] Checklist import error", [
                    'checklist' => $trelloChecklist['name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Import checklist items.
     */
    protected function importChecklistItems(Checklist $checklist, array $items): void
    {
        // Sort by position
        usort($items, fn($a, $b) => ($a['pos'] ?? 0) <=> ($b['pos'] ?? 0));

        $position = 0;
        foreach ($items as $item) {
            try {
                ChecklistItem::create([
                    'checklist_id' => $checklist->id,
                    'content' => $item['name'],
                    'is_completed' => ($item['state'] ?? '') === 'complete',
                    'position' => $position++,
                ]);
                $this->stats['checklist_items_created']++;
            } catch (\Exception $e) {
                Log::warning("[TrelloImport] Checklist item import error", [
                    'item' => $item['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Import comments from Trello actions.
     */
    protected function importComments(array $actions): void
    {
        // Filter for comment actions only
        $comments = array_filter($actions, fn($a) => ($a['type'] ?? '') === 'commentCard');

        // Sort by date (oldest first for proper ordering)
        usort($comments, fn($a, $b) => strtotime($a['date'] ?? 0) <=> strtotime($b['date'] ?? 0));

        foreach ($comments as $comment) {
            try {
                $cardId = $this->cardMapping[$comment['data']['card']['id'] ?? ''] ?? null;
                if (!$cardId) continue;

                $authorName = $comment['memberCreator']['fullName'] ?? 'Trello User';
                $text = $comment['data']['text'] ?? '';
                $date = $comment['date'] ?? null;

                // Create as Activity (comments in this system)
                Activity::create([
                    'card_id' => $cardId,
                    'user_id' => $this->userId,
                    'type' => 'comment',
                    'text' => "[Imported from Trello - {$authorName}]\n{$text}",
                    'created_at' => $date ? \Carbon\Carbon::parse($date) : now(),
                    'updated_at' => $date ? \Carbon\Carbon::parse($date) : now(),
                ]);

                $this->stats['comments_created']++;

            } catch (\Exception $e) {
                Log::warning("[TrelloImport] Comment import error", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Map Trello color names to hex codes.
     */
    protected function mapTrelloColor(string $trelloColor): string
    {
        $colorMap = [
            'green' => '#61bd4f',
            'yellow' => '#f2d600',
            'orange' => '#ff9f1a',
            'red' => '#eb5a46',
            'purple' => '#c377e0',
            'blue' => '#0079bf',
            'sky' => '#00c2e0',
            'lime' => '#51e898',
            'pink' => '#ff78cb',
            'black' => '#344563',
            'green_dark' => '#519839',
            'yellow_dark' => '#d9b51c',
            'orange_dark' => '#d29034',
            'red_dark' => '#b04632',
            'purple_dark' => '#89609e',
            'blue_dark' => '#055a8c',
            'sky_dark' => '#096faf',
            'lime_dark' => '#4bbf6b',
            'pink_dark' => '#cd5a91',
            'black_dark' => '#091e42',
            'green_light' => '#b3f1d0',
            'yellow_light' => '#faf3c0',
            'orange_light' => '#fce8c3',
            'red_light' => '#f5d3ce',
            'purple_light' => '#e4c6f5',
            'blue_light' => '#bcd9ea',
            'sky_light' => '#bdecf3',
            'lime_light' => '#d3f6e4',
            'pink_light' => '#fdd0e8',
            'black_light' => '#c1c7d0',
        ];

        return $colorMap[$trelloColor] ?? '#6b7280'; // Default gray
    }

    /**
     * Send notification about import result.
     */
    protected function sendNotification(?Board $board, bool $success, string $error = ''): void
    {
        $user = config('project-board.user_model')::find($this->userId);
        if (!$user) return;

        if ($success && $board) {
            // Use Nova's notification system if available
            if (class_exists(\Laravel\Nova\Notifications\NovaNotification::class)) {
                $user->notify(
                    \Laravel\Nova\Notifications\NovaNotification::make()
                        ->message("Trello import complete: {$board->name}")
                        ->type('success')
                        ->icon('check-circle')
                );
            }

            // Also log an activity on the board's first card or create a system activity
            Log::info("[TrelloImport] Notification sent to user {$this->userId}", [
                'board' => $board->name,
                'stats' => $this->stats,
            ]);
        } else {
            if (class_exists(\Laravel\Nova\Notifications\NovaNotification::class)) {
                $user->notify(
                    \Laravel\Nova\Notifications\NovaNotification::make()
                        ->message("Trello import failed: {$error}")
                        ->type('error')
                        ->icon('x-circle')
                );
            }
        }
    }
}
