<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;


class TaskController extends Controller
{
    public function create()
    {
        $task = new Task;
        $task->name = 'Lorem ipsum dolor sit ';
        $task->desc = 'Lorem ipsum dolor sit amet';
        $task->save();
        if ($task->save())
            echo ('Done');
        else
            echo ('Error');
    }

    public function update($id)
    {
        $task =  Task::where('id', $id)->get()->first();
        $task->name = 'am?';
        $task->desc = 'Lorem ipsum';
        $task->save();
        if ($task->save())
            echo ('Done');
        else
            echo ('Error');
    }
    public function destroy($id)
    {
        $task =  Task::find($id);
        if ($task->delete())
            echo ('Done');
        else
            echo ('Error');
    }
}
