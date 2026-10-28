<?php

namespace App\Modules\Users\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Presentation\Http\Resources\UserResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
