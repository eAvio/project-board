<?php

namespace Eavio\ProjectBoard\Http\Controllers;

use Eavio\ProjectBoard\Models\Card;
use Eavio\ProjectBoard\Models\Label;
use Eavio\ProjectBoard\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LabelController extends Controller
{
    public function index()
    {
        // Return all labels
        return Label::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
        ]);

        return Label::create($data);
    }

    public function update(Request $request, Label $label)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
        ]);

        $label->update($data);

        return $label;
    }

    public function destroy(Label $label)
    {
        $label->delete();
        return response()->noContent();
    }

    public function sync(Request $request, Card $card)
    {
        $data = $request->validate([
            'labels' => 'present|array',
            'labels.*' => 'exists:labels,id',
        ]);

        $oldLabels = $card->labels->pluck('id')->toArray();
        $newLabels = $data['labels'];

        $added = array_diff($newLabels, $oldLabels);
        $removed = array_diff($oldLabels, $newLabels);

        $card->labels()->sync($data['labels']);

        foreach ($added as $labelId) {
            $label = Label::find($labelId);
            Activity::create([
                'card_id' => $card->id,
                'user_id' => auth()->id(),
                'type' => 'labeled',
                'text' => "added label {$label->name}",
            ]);
        }

        foreach ($removed as $labelId) {
            $label = Label::find($labelId);
            Activity::create([
                'card_id' => $card->id,
                'user_id' => auth()->id(),
                'type' => 'unlabeled',
                'text' => "removed label {$label->name}",
            ]);
        }

        return $card->load('labels');
    }
}
