<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FeedbackStatus;
use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Services\FeedbackService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(FeedbackStatus::class)]])['status'] ?? null;

        return view('admin.feedbacks.index', [
            'status' => $status,
            'statuses' => FeedbackStatus::cases(),
            'feedbacks' => Feedback::with('batch.product', 'batch.farmer', 'customer', 'order')
                ->when($status, fn ($query, $value) => $query->where('status', $value))
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function update(Request $request, Feedback $feedback, FeedbackService $feedbacks): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(FeedbackStatus::class)],
            'response' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $feedbacks->respond($feedback, FeedbackStatus::from($data['status']), $data['response'] ?? null, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return back()->with('status', 'Đã cập nhật phản hồi.');
    }
}
