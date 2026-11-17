<?php

namespace App\Http\Controllers;

use App\Support\ActiveInstance;
use App\Support\PanelInstallation;
use Illuminate\Support\Facades\Auth;

class PanelController extends Controller
{
    public function root()
    {
        if (!PanelInstallation::ensureInstalledState()) {
            return redirect()->route('install.database');
        }

        if (Auth::check()) {
            return redirect()->route('admin.index');
        }

        return redirect()->route('login');
    }

    public function fileManager()
    {
        $activeInstance = ActiveInstance::current();

        return view('admin.file-manager', compact('activeInstance'));
    }
}
