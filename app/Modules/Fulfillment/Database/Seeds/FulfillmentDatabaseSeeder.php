<?php
namespace App\Modules\Fulfillment\Database\Seeds;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class FulfillmentDatabaseSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
		Model::unguard();

		// $this->call('App\Modules\Fulfillment\Database\Seeds\FoobarTableSeeder');
	}

}
