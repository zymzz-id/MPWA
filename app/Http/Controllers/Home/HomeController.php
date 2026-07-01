<?php
/*
Copyright © Magd Almuntaser, OneXGen Technology. All rights reserved.
Project: MPWA Whatsapp Gateway | Multi Device
Licensed under the CC BY-NC-ND 4.0 License.
For details, visit https://creativecommons.org/licenses/by-nc-nd/4.0/.
*/

namespace App\Http\Controllers\Home;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class HomeController extends Controller
{


    public function index(Request $request)
	{

        $user = $request->user()->withCount(['devices','campaigns'])->
        withCount(['blasts as blasts_pending' => function($q){
            return $q->where('status', 'pending');
        }])->withCount(['blasts as blasts_success' => function($q){
            return $q->where('status', 'success');
        }])->withCount(['blasts as blasts_failed' => function($q){
            return $q->where('status', 'failed');
        }])->withCount('messageHistories')->find($request->user()->id);

		if($request->user()->level == 'admin'){
			$user['subscription_status'] = __('Admin');
		}else{
			$user['subscription_status'] = $user->isExpiredSubscription ? 'Expired' : $user->active_subscription;
		}

        $user['expired_subscription_status'] = $user->expiredSubscription;
        return view('theme::home', compact('user'));
    }

}
?>
