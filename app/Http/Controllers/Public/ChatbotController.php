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
        $message = $request->validated('message');
        $reply = $chatbot->answer($message);

        return response()->json(['reply' => $reply, 'products' => $chatbot->productCards($message, $reply)]);
    }
}
