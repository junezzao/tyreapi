<?php namespace App\Extensions;

use Illuminate\Database\Eloquent\Collection;


class CustomCollection extends Collection {
	
	public function toAPIResponse()
	{
		return array_map(function ($value) {
			return method_exists($value,'toAPIResponse') ? $value->toAPIResponse() : $value;
        }, $this->items);
	}

	public function deactivate()
	{
		return array_map(function ($value) {
			return method_exists($value,'deactivate') ? $value->deactivate() : $value;
        }, $this->items);
	}
 }