<?php

namespace Eavio\ProjectBoard\Http\Controllers;

use Eavio\ProjectBoard\Models\Card;
use Eavio\ProjectBoard\Models\Comment;
use Eavio\ProjectBoard\Notifications\CardMentioned;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\User;

class CommentController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Card $card)
    {
        $data = $request->validate([
            'content' => 'required|string',
        ]);

        $comment = $card->comments()->create([
            'content' => $data['content'],
            'user_id' => auth()->id(),
        ]);

        $this->notifyMentions($comment);

        return $comment->load('user', 'reactions.user');
    }

    public function update(Request $request, Comment $comment)
    {
        // $this->authorize('update', $comment); // Uncomment if Policy exists
        
        $data = $request->validate([
            'content' => 'required|string',
        ]);

        $comment->update([
            'content' => $data['content'],
        ]);

        $this->notifyMentions($comment);

        return $comment->load('user', 'reactions.user');
    }

    public function toggleReaction(Request $request, Comment $comment)
    {
        $data = $request->validate([
            'emoji' => 'required|string',
        ]);

        $existing = $comment->reactions()
            ->where('user_id', auth()->id())
            ->where('emoji', $data['emoji'])
            ->first();

        if ($existing) {
            $existing->delete();
            $action = 'removed';
        } else {
            $comment->reactions()->create([
                'user_id' => auth()->id(),
                'emoji' => $data['emoji'],
            ]);
            $action = 'added';
        }

        return $comment->load('user', 'reactions.user');
    }

    public function destroy(Comment $comment)
    {
        if ($comment->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }
        
        $comment->delete();
        return response()->noContent();
    }

    private function notifyMentions(Comment $comment)
    {
        preg_match_all('/@([\w]+)/', $comment->content, $matches);
        if (empty($matches[1])) return;

        $names = array_unique($matches[1]);
        // Using 'like' for more flexible matching or exact match? Exact is safer for automated systems.
        // Assuming names are simple. If users have spaces, this regex @([\w]+) only catches first word.
        // This is a limitation of simple text mentions.
        $users = User::whereIn('name', $names)->get();

        foreach ($users as $user) {
            if ($user->id === $comment->user_id) continue;

            // Debounce key: User + Card ID. 
            // We use commentable_id assuming it is a Card.
            $key = "mention_debounce_{$user->id}_{$comment->commentable_type}_{$comment->commentable_id}";
            
            if (Cache::has($key)) continue;

            // Send notification with 15 min delay
            // Using try-catch block to avoid crashing if mail is not configured
            try {
                $user->notify((new CardMentioned($comment))->delay(now()->addMinutes(15)));
                Cache::put($key, true, now()->addMinutes(15));
            } catch (\Exception $e) {
                // Log error or ignore
            }
        }
    }
}
