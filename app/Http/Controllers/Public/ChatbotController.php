<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatbotMessageRequest;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;

class ChatbotController extends Controller
{
    public function __invoke(ChatbotMessageRequest $request, ChatbotService $chatbot): JsonResponse
    {
        return response()->json(['reply' => $chatbot->answer($request->validated('message'))]);
    }
}
