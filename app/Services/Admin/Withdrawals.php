<?php

namespace App\Services\Admin;

use App\Models\Withdraw;

class Withdrawals
{
	public static function get()
	{
		$withdrawals = Withdraw::select(
			'id',
			'user_id',
			'amount',
			'created_at',
			'status'
		)
			->latest()
			->with('user:id,uid,email,first_name,last_name,avatar')
			->paginate();

		return $withdrawals;
	}

}