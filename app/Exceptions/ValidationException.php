<?php
namespace App\Exceptions;
use Illuminate\Contracts\Validation\Validator;
use Exception;

class ValidationException extends Exception
{
	protected $validator;
	public function __construct(Validator $validator)
	{
		parent::__construct("Validation Error",422);
		$this->validator = $validator;
	}

	public function errors()
	{
		return $this->validator->errors();
	}
}