<?php

namespace App\Http\Controllers;

use App\Enums\CategoryStatus;
use App\Enums\CategoryType;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::with('icon')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('categories.index', compact('categories'));
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Category::create([
            'type' => CategoryType::from((int) $data['type']),
            'status' => CategoryStatus::Active,
            'name' => $data['name'],
            'icon_id' => $data['icon_id'] ?? null,
        ]);

        return redirect()->route('categories.index')->with('status', 'category-created');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();

        // Type stays as created; only name, status and icon are editable.
        $category->update([
            'status' => CategoryStatus::from((int) $data['status']),
            'name' => $data['name'],
            'icon_id' => $data['icon_id'] ?? null,
        ]);

        return redirect()->route('categories.index')->with('status', 'category-updated');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('categories.index')->with('status', 'category-deleted');
    }
}
