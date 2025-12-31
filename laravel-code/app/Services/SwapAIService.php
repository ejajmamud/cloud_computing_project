<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SwapAIService
{
    /**
     * Connects to Groq's API.
     * Updated to use the latest stable Llama 3.3 model.
     */
    public function askSwapAI($question)
    {
        $apiKey = env('GROQ_API_KEY');
        $url = "https://api.groq.com/openai/v1/chat/completions";

        // Current stable model as of late 2025
        $model = 'llama-3.3-70b-versatile'; 

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->post($url, [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system', 
                        'content' => "You are Swap AI, an expert academic tutor for UniKL. Keep answers concise, helpful, and encouraging."
                    ],
                    [
                        'role' => 'user', 
                        'content' => $question
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 1024
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['choices'][0]['message']['content'] ?? "Groq answered, but the content was empty.";
            }

            // Log the specific error from Groq to help debugging
            Log::error("Groq API Error: " . $response->body());
            
            $errorMsg = $response->json()['error']['message'] ?? "Unknown Groq Error";
            
            // Auto-fallback suggestion if this model also gets deprecated later
            if (str_contains($errorMsg, 'deprecated') || str_contains($errorMsg, 'decommissioned')) {
                 return "System Update Required: The AI model '$model' has been updated by Groq. Please check console.groq.com/docs/models for the new ID.";
            }

            return "Tutor Error: " . $errorMsg;

        } catch (\Exception $e) {
            Log::error("Swap AI Service Crash: " . $e->getMessage());
            return "System Error: " . $e->getMessage();
        }
    }
}
