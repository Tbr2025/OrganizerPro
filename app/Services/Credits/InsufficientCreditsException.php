<?php

declare(strict_types=1);

namespace App\Services\Credits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Not enough credits. Thrown before anything changes, and rendered as a plain
 * message with the shortfall and a way to top up — never a 500.
 */
class InsufficientCreditsException extends RuntimeException
{
    public function __construct(public readonly int $required, public readonly int $balance, public readonly string $term = 'Credits')
    {
        parent::__construct("This needs {$required} {$term}; your balance is {$balance}.");
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        $walletUrl = route('admin.credits.index');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage() . ' Top up from the ' . $this->term . ' page.',
                'required' => $this->required,
                'balance' => $this->balance,
                'wallet_url' => $walletUrl,
            ], 402);
        }

        return back()->withInput()->with('error', $this->getMessage() . ' Top up from the ' . $this->term . ' page: ' . $walletUrl);
    }
}
