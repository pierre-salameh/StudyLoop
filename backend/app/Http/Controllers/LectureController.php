<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lecture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json($lecture->fresh(), 201);
    }

    public function show(Request $request, int $lecture): Lecture
    {
        return $this->ownedLecture($request, $lecture);
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