<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    use ResponseTrait;

    public function countByUser(Request $request)
    {
        $count = Comment::where('user_id', $request->user()->id)
            ->where('status', 'approved')
            ->count();

        return $this->success(['count' => $count]);
    }

    public function getAllByUser(Request $request)
    {
        $userCols = ['users.id', 'users.first_name', 'users.last_name', 'users.phone', 'users.email'];
        $baseQuery = Comment::where('user_id', $request->user()->id)->where('status', 'approved');

        $count = (clone $baseQuery)->count();

        $comments = (clone $baseQuery)
            ->with([
                'sender' => fn ($q) => $q->select($userCols),
                'creator' => fn ($q) => $q->select($userCols),
            ])
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'comments' => $comments,
            'count' => $count,
        ]);
    }
}
