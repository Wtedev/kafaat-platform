<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use Illuminate\Http\Request;

class PublicLearningPathController extends Controller
{
    public function index()
    {
        abort(404);
    }

    public function show(LearningPath $learningPath)
    {
        abort(404);
    }

    public function register(Request $request, LearningPath $learningPath)
    {
        abort(404);
    }
}
