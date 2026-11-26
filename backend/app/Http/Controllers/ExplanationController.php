<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateExplanation;
use App\Models\Concept;
use App\Services\ConceptExplainer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExplanationController extends Controller
{
    public function store(Request $request, int $concept): JsonResponse
    {
        $data = $request->validate([
            'style' => ['required', 'string', Rule::in(array_keys(ConceptExplainer::STYLES))],
        ]);

        // المفهوم ملك المستخدم إذا كانت محاضرته في مقرر يملكه
        $model = Concept::whereHas(
            'lecture.course',
            fn ($q) => $q->where('user_id', $request->user()->id),
        )->findOrFail($concept);

        $record = $model->explanations()->firstOrNew(['style' => $data['style']]);

        if ($record->exists) {
            $busy = in_array($record->status, ['pending', 'processing'], true)
                && $record->updated_at->gt(now()->subHours(2));

            // لا نكرر العمل إذا كان الشرح جاهزاً أو قيد التوليد
            if ($record->status === 'ready' || $busy) {
                return response()->json($record);
            }
        }

        $record->fill([
            'status' => 'pending',
            'attempts' => 0,
            'explanation' => null,
            'example' => null,
            'log' => null,
        ]);
        $record->save();

        GenerateExplanation::dispatch($record->id);

        return response()->json($record, 202);
    }
}