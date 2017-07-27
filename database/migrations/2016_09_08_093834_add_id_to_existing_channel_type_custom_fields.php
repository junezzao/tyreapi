<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\ChannelType;

class AddIdToExistingChannelTypeCustomFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $channelTypes = ChannelType::withTrashed()->get();

        foreach ($channelTypes as $channelType) {
            if (!empty($channelType->fields)) {
                $fields = json_decode($channelType->fields, true);

                foreach ($fields as $index => $field) {
                    if (!empty($field['id'])) {
                        break;
                    }

                    $fields[$index]['id'] = $index + 1;
                }

                $channelType->fields = json_encode($fields);
                $channelType->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $channelTypes = ChannelType::withTrashed()->get();

        foreach ($channelTypes as $channelType) {
            if (!empty($channelType->fields)) {
                $fields = json_decode($channelType->fields, true);

                foreach ($fields as $index => $field) {
                    if (empty($field['id'])) {
                        break;
                    }

                    unset($fields[$index]['id']);
                }

                $channelType->fields = json_encode($fields);
                $channelType->save();
            }
        }
    }
}
