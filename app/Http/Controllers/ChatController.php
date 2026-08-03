<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat');
    }

    public function send(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $messages = $request->input('history', []);
        $messages[] = ['role' => 'user', 'content' => $request->input('message')];

        try {
            $response = OpenAI::chat()->create([
                'model' => 'phi3',
                'messages' => array_merge(
                    [['role' => 'system', 'content' => 'You are a helpful AI assistant. Be concise and friendly.']],
                    $messages
                ),
            ]);

            $reply = $response->choices[0]->message->content;

            return response()->json([
                'reply' => $reply,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get response from AI. Make sure Ollama is running.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
