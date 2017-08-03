<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use App\Models\User;
use Bican\Roles\Models\Role;
use Log;

class UpdateUserSubscriptionPlan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:UpdateUserSubscriptionPlan
                            {--date= : format Y-m-d}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update user subcription plan daily';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // command variables
        $date = date('Y-m-d', strtotime((!is_null($this->option('date'))) ? $this->option('date') : "now"));

        $this->info('Running... command:UpdateUserSubscriptionPlan for '.$date);
        Log::info('Running... command:UpdateUserSubscriptionPlan for '.$date);

        $subscriptions = Subscription::where('start_date', $date)->where('status', 'Upcoming')->get();
        foreach($subscriptions as $subs) {
            $role = Role::findOrFail($subs->role_id);

            User::where('id', $subs->user_id)->update([
                'category' => $role->name,
                'subscription_id' => $subs->id
            ]);

            $subs->status = 'Active';
            $subs->save();

            Subscription::where('user_id', $subs->user_id)->where('start_date', '<', $subs->start_date)->update([
                'status' => 'Expired'
            ]);
        }
    }
}
