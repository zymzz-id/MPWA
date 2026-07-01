<?php
/*
Copyright © Magd Almuntaser, OneXGen Technology. All rights reserved.
Project: MPWA Whatsapp Gateway | Multi Device
Licensed under the CC BY-NC-ND 4.0 License.
For details, visit https://creativecommons.org/licenses/by-nc-nd/4.0/.
*/

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    public function index()
	{
		$plansClass = 'Plugins\\Billing\\Models\\Plans';
		$plans = class_exists($plansClass) ? $plansClass::where('status', 1)->orderBy('created_at', 'asc')->get() : collect();
		return view('index::home', compact('plans'));
	}
}
