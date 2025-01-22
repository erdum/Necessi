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

	public static function details(Withdraw $withdrawal)
	{
		$history = $withdrawal->user->withdraws()
			->whereNot('id', $withdrawal->id)
			->paginate();

		$history->getCollection()->transform(function ($withdraw) {
			return [
				'id' => $withdraw->id,
				'request_date' => $withdraw->created_at->format('Y-m-d'),
				'amount' => $withdraw->amount,
				'type' => 'withdrawal',
			];
		});

		return [
			'user' => [
				'id' => $withdrawal->user->id,
				'uid' => $withdrawal->user->uid,
				'email' => $withdrawal->user->email,
			],
			'id' => $withdrawal->id,
			'amount' => $withdrawal->amount,
			'status' => $withdrawal->status,
			'request_date' => $withdrawal->created_at->format('Y-m-d'),
			'history' => $history,
		];
	}
}