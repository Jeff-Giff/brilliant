<?php

namespace App\Http\Controllers;

use App\Models\Problem;
use App\Models\Solve;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SolveController extends Controller
{
    //

    public function store(Request $request)
    {
        $attributes = $request->validate([
            'solution' => 'required', // FIXME: Validate problem_id
        ]);

        $problemId = $request->input('id');
        $p = Problem::where('id', (int)$problemId)->first();

        if (!$p) {
            throw new ModelNotFoundException();
        }

        $correct = false;
        if ($p->solution == $request->input('solution')) {
            $correct = true;
        }

        Solve::create([
            'solution' => $request->input('solution'),
            'user_id' => Auth::id(),
            'problem_id' => $request->input('id'),
            'correct' => $correct
        ]);


        // FIXME: Validation checking, successful?
        //return back()->withErrors([
        //    'email' => 'The provided credentials do not match our records.',
        //]);

        return back();
    }
}
