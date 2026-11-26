<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lecture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Jobs\ExtractLectureConcepts;

class LectureController extends Controller
{
    public function index(Request $request, int $course)
    {
        return $this->ownedCourse($request, $course)
            ->lectures()
            ->select('id', 'course_id', 'title', 'status', 'created_at')
            ->latest()
            ->get();
    }

    public function store(Request $request, int $course): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'min:20', 'max:200000'],
        ]);

             $lecture = $this->ownedCourse($request, $course)
            ->lectures()
            ->create($data);

        ExtractLectureConcepts::dispatch($lecture->id);

        return response()->json($lecture->fresh(), 201);
    }

     public function show(Request $request, int $lecture): Lecture
    {
                return $this->ownedLecture($request, $lecture)->load('concepts.explanations');
    }

    public function update(Request $request, int $lecture): Lecture
    {
        $model = $this->ownedLecture($request, $lecture);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string', 'min:20', 'max:200000'],
        ]);

        $model->update($data);

        return $model;
    }

    public function destroy(Request $request, int $lecture): JsonResponse
    {
        $this->ownedLecture($request, $lecture)->delete();

        return response()->json(['message' => 'تم حذف المحاضرة.']);
    }



        public function reprocess(Request $request, int $lecture): JsonResponse
    {
        $model = $this->ownedLecture($request, $lecture);

        // نمنع التكرار أثناء المعالجة، إلا إذا مرّت ساعتان (يعني أن العامل توقف في منتصف العمل)
        $busy = in_array($model->status, ['pending', 'processing'], true)
            && $model->updated_at->gt(now()->subHours(2));

        if ($busy) {
            return response()->json(['message' => 'المحاضرة قيد المعالجة بالفعل.'], 409);
        }

        $model->status = 'pending';
        $model->save();

        ExtractLectureConcepts::dispatch($model->id);

        return response()->json($model);
    }

    private function ownedCourse(Request $request, int $id): Course
    {
        return $request->user()->courses()->findOrFail($id);
    }

    // المحاضرة تُعتبر ملكاً للمستخدم إذا كان مقررها ملكه
    private function ownedLecture(Request $request, int $id): Lecture
    {
        return Lecture::whereHas(
            'course',
            fn ($q) => $q->where('user_id', $request->user()->id),
        )->findOrFail($id);
    }
}