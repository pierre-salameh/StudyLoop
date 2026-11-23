<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->courses()->latest()->get();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $course = $request->user()->courses()->create($data);

        return response()->json($course, 201);
    }

    public function show(Request $request, int $course): Course
    {
        return $this->findOwned($request, $course);
    }

    public function update(Request $request, int $course): Course
    {
        $model = $this->findOwned($request, $course);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $model->update($data);

        return $model;
    }

    public function destroy(Request $request, int $course): JsonResponse
    {
        $this->findOwned($request, $course)->delete();

        return response()->json(['message' => 'تم حذف المقرر.']);
    }

    // نبحث داخل مقررات المستخدم الحالي فقط، فإن لم يكن المقرر له نحصل على 404
    private function findOwned(Request $request, int $id): Course
    {
        return $request->user()->courses()->findOrFail($id);
    }
}