<?php

namespace App\Modules\Channels\Repositories\Eloquent;

use App\Repositories\Repository;
use App\Modules\Channels\Repositories\Contracts\ChannelTypeRepositoryContract;
use App\Models\Admin\ChannelType;
use App\Models\Admin\Channel;
use DB;

class ChannelTypeRepository extends Repository implements ChannelTypeRepositoryContract
{
    protected $model;

    protected $role;

    public function __construct(ChannelType $model)
    {
        $this->model = $model;
        parent::__construct();
    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'App\Models\Admin\ChannelType';
    }

    /**
     * @param $id
     * @return mixed
     */
    public function delete($id)
    {
        $channel_type = $this->model->with('channels')->find($id);

        if (count($channel_type->channels) > 0) {
            return "There are channels of channel type " . $channel_type->name;
        }
        else {
            return $this->model->destroy($id);
        }
    }

    /**
     * @param array $data
     * @param $id
     * @param string $attribute
     * @return mixed
     */
    public function update(array $data, $id, $attribute="id")
    {\Log::info($data);
        $this->makeModel();

        $newFields = json_decode($data['fields'], true);

        $newFieldsLabel = array_pluck($newFields, 'api');
        $newFieldsId = array_pluck($newFields, 'id');

        $channelType = $this->model->find($id);
        $oldFields = json_decode($channelType->fields, true);
        $oldFields = is_array($oldFields) ? $oldFields : array(); // to ensure $oldFields is always an array
        if (!empty($data['shipping_rate'])) {
            $shippingRate = json_decode($data['shipping_rate'], true);
         }else{
             $shippingRate = '';
         }

        $channels = Channel::with('channel_detail')->where('channel_type_id', '=', $id)->get();
        foreach ($channels as $channelId => $channel) {
            if (!is_null($channel->channel_detail)) {
                $channelFields = json_decode($channel->channel_detail->extra_info, true);
            }else{
                $channelFields = '';
            }
            
            $channelFields = is_array($channelFields) ? $channelFields : array(); // to ensure $channelFields is always an array

            // case: custom field was renamed
            foreach ($oldFields as $oldField) {
                if (!in_array($oldField['api'], $newFieldsLabel) && in_array($oldField['id'], $newFieldsId)) {

                    foreach ($newFields as $newField) {
                        if ($newField['id'] == $oldField['id']) {

                            if(isset($channelFields[$oldField['api']])) {
                                $channelFields[$newField['api']] = $channelFields[$oldField['api']];
                                unset($channelFields[$oldField['api']]);
                            }

                            break;
                        }
                    }
                }
            }

            // case: custom field was deleted
            foreach ($channelFields as $key => $value) {
                if (!in_array($key, $newFieldsLabel)) {
                    unset($channelFields[$key]);
                }
            }

            if (!is_null($channel->channel_detail)) {
                $channels[$channelId]->channel_detail->extra_info = json_encode($channelFields);
                if ($channel->channel_detail->shipping_default == 1) {
                    $channels[$channelId]->channel_detail->shipping_rate = json_encode($shippingRate);
                }
                $channels[$channelId]->channel_detail->save();
            }
        }

        return $this->model->where($attribute, '=', $id)->update($data);
    }

    public function updateStatus($id, $status) {
        DB::beginTransaction();

        $channelType = ChannelType::find($id);
        $channelType->status = $status;
        $channelType->save();

        if ($status == 'Inactive') {
            $channels = Channel::with('channel_detail')->where('channel_type_id', '=', $id)->get();

            foreach ($channels as $channel) {
                $channel->status = $status;
                $channel->save();

                $channel->merchants()->detach();
            }
        }

        DB::commit();

        return true;
    }

    public function getManifestActiveChannels() {
        $channelTypes = ChannelType::whereIn('id', array_keys(config('globals.channel_type_picking_manifest')))
                        ->where('status', '=', 'Active')
                        ->orderBy('name', 'asc')
                        ->get();

        $data = array();
        foreach ($channelTypes as $channelType) {
            $data[$channelType->id] = $channelType->name;
        }

        return $data;
    }
}
