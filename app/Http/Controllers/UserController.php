<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    public function create()
    {
        $user = new User;
        $user->name = 'ali';
        $user->email = 'ali';
        $user->password = '123';
        $user->save();
        if ($user->save())
            echo ('Done');
        else
            echo ('Error');
    }
}
