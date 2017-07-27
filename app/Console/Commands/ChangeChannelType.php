<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;
use App\Models\Admin\ProductThirdParty;
use App\Models\Admin\ThirdPartySync;
use App\Models\Admin\ThirdPartySyncArchive;
use DB;

class ChangeChannelType extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:changeChannelType
                                {channel_id : The target channel (ID) to be changed}
                                {channel_type_id : The new channel type (ID) for the targeted channel}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Changes the channel type of a channel';

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
        $channelId = $this->argument('channel_id');
        $channelTypeId = $this->argument('channel_type_id');

        $channel = Channel::with('channel_type')->find($channelId);
        $channelType = ChannelType::find($channelTypeId);

        if (is_null($channel)) {
            $this->error('Channel ID ' . $channelId . ' not found.');
            return;
        }

        if (is_null($channelType)) {
            $this->error('Channel type ID ' . $channelTypeId . ' not found.');
            return;
        }

        if ($this->confirm('This will update channel "' . $channel->name . '" to new channel type "' . $channelType->name . '". Do you wish to continue?')) {
            try {
                $this->info('Updating...');

                DB::beginTransaction();

                if ($channel->channel_type->third_party == 1) {
                    ProductThirdParty::where('channel_id', '=', $channel->id)->update(array('third_party_name' => $channelType->name));
                    ThirdPartySync::where('channel_id', '=', $channel->id)->update(array('channel_type_id' => $channelType->id));
                    ThirdPartySyncArchive::where('channel_id', '=', $channel->id)->update(array('channel_type_id' => $channelType->id));
                }

                $channel->channel_type_id = $channelType->id;
                $channel->save();

                DB::commit();

                $this->info('Done.');
            }
            catch (Exception $e) {
                $this->error('Error: ' . $e->getMessage() . ' at line ' . $e->getLine());
                $this->error($e->getTraceAsString());
            }
        }
    }
}
