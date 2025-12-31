<?php

namespace App\Http\Controllers;

use App\Models\PeerExchange;
use App\Models\Category;
use App\Models\ChatHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\SwapAIService;
use Illuminate\Support\Facades\Log;

class SkillSwapController extends Controller
{
    // --- MARKETPLACE FUNCTIONS ---
    public function index() {
        $posts = PeerExchange::with('creator')->where('status', 'open')->orderBy('created_at', 'desc')->paginate(10);
        return view('skillswap.index', compact('posts'));
    }

    public function create() {
        $categories = Category::select('id', 'name')->active()->get();
        return view('skillswap.create', compact('categories'));
    }

    public function store(Request $request) {
        $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'required|string',
            'type' => 'required|in:offer,request',
            'skill_tag' => 'required|string|max:50',
        ]);
        PeerExchange::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'skill_tag' => $request->skill_tag,
            'status' => 'open',
        ]);
        return redirect()->route('skillswap.index')->with('success', 'Post created!');
    }

    // --- AI TUTOR FUNCTIONS ---
    public function aiIndex() {
        $history = collect();
        if (Auth::check()) {
            try {
                $history = ChatHistory::where('user_id', Auth::id())->orderBy('created_at', 'asc')->limit(50)->get();
            } catch (\Exception $e) {
                Log::error("History Load Failed: " . $e->getMessage());
            }
        }
        return view('skillswap.ai_assistant', compact('history'));
    }

    public function askAI(Request $request) {
        if (!$request->ajax()) return response()->json(['success' => false, 'answer' => 'Invalid request.'], 400);
        try {
            $question = $request->input('question');
            if (!$question) return response()->json(['success' => false, 'answer' => 'Empty question.']);

            // Save History only if user is logged in
            if (Auth::check()) {
                ChatHistory::create(['user_id' => Auth::id(), 'role' => 'user', 'content' => $question]);
            }

            $aiService = new SwapAIService();
            $answer = $aiService->askSwapAI($question);

            if (Auth::check() && !str_contains($answer, 'Error')) {
                ChatHistory::create(['user_id' => Auth::id(), 'role' => 'ai', 'content' => $answer]);
            }
            return response()->json(['success' => true, 'answer' => $answer]);
        } catch (\Exception $e) {
            Log::error("AI Error: " . $e->getMessage());
            return response()->json(['success' => false, 'answer' => 'AI Tutor is currently unavailable.']);
        }
    }

    public function getChatHistory() {
        if (!Auth::check()) return response()->json(['success' => false, 'history' => []]);
        try {
            $history = ChatHistory::where('user_id', Auth::id())->orderBy('created_at', 'asc')->limit(20)->get();
            return response()->json(['success' => true, 'history' => $history]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'history' => []]);
        }
    }

    public function clearChat() {
        if (Auth::check()) ChatHistory::where('user_id', Auth::id())->delete();
        return redirect()->back();
    }
}
