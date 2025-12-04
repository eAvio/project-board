<?php

namespace Eavio\ProjectBoard\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Eavio\ProjectBoard\Models\Card;
use Eavio\ProjectBoard\Models\Comment;
use Eavio\ProjectBoard\Models\ChecklistItem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->input('query');
        if (!$query || strlen($query) < 2) {
            return response()->json([]);
        }

        $results = [];

        // Search Cards
        $cards = Card::where('title', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->with(['column.board'])
            ->limit(5)
            ->get();

        foreach ($cards as $card) {
            $results[] = [
                'type' => 'card',
                'title' => $card->title,
                'preview' => $card->description ? substr(strip_tags($card->description), 0, 60) . '...' : '',
                'card_id' => $card->id,
                'board_id' => $card->column->board->id ?? null,
                'board_name' => $card->column->board->name ?? 'Unknown',
                'column_name' => $card->column->name ?? 'Unknown',
            ];
        }

        // Search Comments
        $comments = Comment::where('content', 'like', "%{$query}%")
            ->where('commentable_type', Card::class)
            ->with(['commentable.column.board', 'user'])
            ->limit(5)
            ->get();

        foreach ($comments as $comment) {
            $card = $comment->commentable;
            if ($card) {
                $results[] = [
                    'type' => 'comment',
                    'title' => 'Comment on: ' . $card->title,
                    'preview' => substr(strip_tags($comment->content), 0, 60) . '...',
                    'card_id' => $card->id,
                    'board_id' => $card->column->board->id ?? null,
                    'board_name' => $card->column->board->name ?? 'Unknown',
                    'user_name' => $comment->user->name ?? 'Unknown',
                ];
            }
        }
        
        // Search Checklist Items
        $items = ChecklistItem::where('content', 'like', "%{$query}%")
            ->with(['checklist.card.column.board'])
            ->limit(5)
            ->get();
            
        foreach ($items as $item) {
            if ($item->checklist && $item->checklist->card) {
                $card = $item->checklist->card;
                $results[] = [
                    'type' => 'checklist',
                    'title' => 'Checklist in: ' . $card->title,
                    'preview' => $item->content,
                    'card_id' => $card->id,
                    'board_id' => $card->column->board->id ?? null,
                    'board_name' => $card->column->board->name ?? 'Unknown',
                ];
            }
        }

        // Search Attachments
        try {
            $media = Media::where('model_type', Card::class)
                 ->where(function($q) use ($query) {
                     $q->where('file_name', 'like', "%{$query}%")
                       ->orWhere('name', 'like', "%{$query}%");
                 })
                 ->limit(5)
                 ->get();
                 
            foreach ($media as $item) {
                $card = Card::find($item->model_id);
                if ($card) {
                    $results[] = [
                        'type' => 'attachment',
                        'title' => 'File: ' . $item->file_name,
                        'preview' => $item->mime_type,
                        'card_id' => $card->id,
                        'board_id' => $card->column->board->id ?? null,
                        'board_name' => $card->column->board->name ?? 'Unknown',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Ignore if Media table doesn't exist or other error
        }

        return response()->json($results);
    }
}
