<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function getMenus(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['menus' => []], 200);
        }

        $user->load('role');

        if (!$user->role) {
            return response()->json(['menus' => []], 200);
        }

        $roleMenus = [
            'super_admin' => ['dashboard', 'project', 'profile', 'setting', 'collaboration'],
            'super_admin' => ['dashboard', 'project', 'profile', 'setting', 'collaboration'],
            'manager' => ['dashboard', 'project', 'profile', 'setting', 'collaboration'],
            'subcontractor' => ['dashboard', 'project', 'profile', 'setting', 'collaboration'],
            'user' => ['dashboard', 'project', 'profile', 'setting', 'collaboration'],
        ];

        $menus = $roleMenus[$user->role->name] ?? [];

        return response()->json(['menus' => $menus], 200);
    }
}
