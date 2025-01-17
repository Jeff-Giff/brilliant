<?php

use Illuminate\Support\Facades\Route;

use App\Models\Problem;
use App\Models\Solve;
use App\Http\Controllers\ProblemController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\SolveController;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('problems');
});

// Generate periodically
Route::get('/initialproblems', function () {
    // FIXME: If doesn't exist then trigger generate and wait until generated
    $problems = file_get_contents('../../initialproblems.json');
    return json_decode($problems);
});

// Generated nightly/weekly
Route::get('/allproblems', function () {
    // FIXME: If doesn't exist then trigger generate and wait until generated
    // FIXME: Split into multiple jsons of 10k size, order by date, fetch older sets on request (pagination)
    //$problems = file_get_contents('../../sortedproblems.json');
    //return json_decode($problems);
    $p = Problem::get(['id', 'title', 'category_id', 'level']);
    $problems = [];
    foreach ($p as $problem) {
        if(!$problem) continue;
        $problems[] = [
            'n' => $problem->title,
            'p' => "problem/" . $problem->id,
            'c' => $problem->category_id,
            'l' => $problem->level,
        ];
    }

    $output = json_encode($problems);
    return $output;
});

Route::get('/problemspaginatecount/{categoryId}/{level}', function ($categoryId, $level) {
    // FIXME: There's gotta be a cleaner way to do this
    if ($categoryId > 0 && $level > 0) {
        $count = Problem::where('category_id', $categoryId)->where('level', $level)->count();
    } else if ($categoryId > 0) {
        $count = Problem::where('category_id', $categoryId)->count();
    } else if ($level > 0) {
        $count = Problem::where('level', $level)->count();
    } else {
        $count = Problem::count();
    }

    return json_encode(['count' => $count]);
})->where(['categoryId' => '[0-9]', 'level' => '[0-9]']);


Route::get('/problemspaginate/{categoryId}/{level}/{offset}', function ($categoryId, $level, $offset) {
    $query = Problem::orderBy('id', 'desc');
    if ($categoryId > 0) {
        $query = $query->where('category_id', $categoryId);
    }

    if ($level > 0) {
        $query = $query->where('level', $level);
    }

    // offset as offset within filter
    if ($offset > 0) {
        $query = $query->offset($offset);
    }

    $p = $query->limit(1000)->get(['id', 'title', 'category_id', 'level']);
    // FIXME: Return as json w/out manual json_encode
    $problems = [];
    foreach ($p as $problem) {
        $problems[] = [
            'n' => $problem->title,
            'p' => "problem/" . $problem->id,
            'c' => $problem->category_id,
            'l' => $problem->level,
        ];
    }

    return json_encode($problems);
})->where(['categoryId' => '[0-9]', 'level' => '[0-9]', 'offset' => '[0-9]+']);

Route::get('/newproblems', function () {
    $p = Problem::where('created_at', '>=', now()->subWeek())->limit(1000)->get(['id', 'title', 'category_id', 'level']);
    $problems = [];
    foreach ($p as $problem) {
        $problems[] = [
            'n' => $problem->title,
            'p' => "problem/" . $problem->id,
            'c' => $problem->category_id,
            'l' => $problem->level,
        ];
    }

    return json_encode($problems);
});

Route::get('/problems', function() {
    return view('problems');
})->name('problems');

Route::get('/brilliantexport/problems/{problemFirst}/{problem}', function ($problemId) {
    return File::get(public_path() . "/brilliantexport/problems/$problemId/$problemId.html");
})->where('problem', '[A-Za-z0-9_-]+');

Route::get('/problem/{problem}', function ($problemId) {
    $p = Problem::where('id', (int)$problemId)->with(['topic', 'author', 'comments.author:id,name,created_at'])->first();

    if (!$p) {
        throw new ModelNotFoundException();
    }

    //$p = $p[0];
    $users[$p->author->id] = ['name' => $p->author->name];
    $comments = [];
    foreach ($p->comments as $comment) {

        if (!array_key_exists($comment->author_id, $users)) {
            $users[$comment->author_id] = ['name' => $comment->author->name];
        }

        $comments[] = [
            'id' => $comment->id,
            'author' => $comment->author_id,
            'body' => $comment->body,
            'parent_comment_id' => $comment->parent_comment_id,
            'date' => $comment->created_at
        ];
    }

    $json = ['problem' => [
        'id' => $p->id,
        'title' => $p->title,
        'topic' => $p->topic->name,
        'body' => $p->body,
        'level' => $p->level,
        'author' => $p->author_id,
        'users' => $users,
        'comments' => $comments,
        'source' => $p->source
    ], 'source' => $p->source,
    'user' => [
        'id' => Auth::id()
    ]];


    if (Auth::id()) {
        $solve = Solve::where('problem_id', (int)$problemId)->where('user_id', Auth::id())->first();
        if ($solve) {
            $json['solve'] = [
                'solution' => $solve->solution,
                'correct' => $solve->correct,
                'date' => $solve->created_at
            ];
        }
    }


    return view('problem', $json);
    //return view('problem', ['problem' => Problem::find(1)->with(['topic', 'author', 'comments.author:name'])->get()]);
})->whereNumber('problem');


Route::get('/addproblem', [ProblemController::class, 'create'])->middleware(['auth'])->name('addproblem');
//    return view('addproblem');
//})->middleware(['auth']);

Route::get('/randomproblem', function() {
    $count = intval(Problem::count());
    $randomIdx = rand(1, $count);
    return redirect('/problem/' . $randomIdx);
})->name('randomproblem');

Route::post('/addproblem', [ProblemController::class, 'store'])->middleware(['auth']);
Route::get('/edit/{problem}', [ProblemController::class, 'edit'])->middleware(['auth'])->whereNumber('problem')->name('editproblem');
Route::post('/edit', [ProblemController::class, 'change'])->middleware(['auth']);
Route::post('/comment', [CommentController::class, 'store'])->middleware(['auth']);
Route::post('/editcomment', [CommentController::class, 'change'])->middleware(['auth']);
Route::post('/solve', [SolveController::class, 'store'])->middleware(['auth']);

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

require __DIR__.'/auth.php';
