<?php

namespace App\Http\Controllers;

use App\Models\HelpEntry;

class HelpController extends Controller
{
    public function index()
    {
        $sections = HelpEntry::orderBy('sort')->orderBy('id')->get()->groupBy('section');

        return view('help.index', compact('sections'));
    }
}
