<?php
namespace App\Repositories;

use App\Repositories\Contracts\UserRepository as UserRepositoryInterface;
use App\Models\User;
use App\Models\Admin\Channel;
use Bican\Roles\Models\Role;
use Activity;
use Authorizer;

class UserRepository extends Repository implements UserRepositoryInterface
{
    protected $model;

    protected $role;

    protected $skipCriteria = true;

    public function __construct(User $model, Role $role)
    {
        $this->model = $model;
        $this->role = $role;
        parent::__construct();
    }
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'App\Models\User';
    }

    /**
     * @param array $data
     * @param $id
     * @param string $attribute
     * @return mixed
     */
    public function update(array $data, $id)
    {
        $user = $this->find($id);
        $user->update($data);
        
        if (!empty($data['channels'])) {
            $user = $this->model->with('channels')->find($id);

            $input_channels = $data['channels'];
            $current_channels = array();

            foreach ($user->channels as $channel) {
                $current_channels[] = $channel->id;
            }

            $new_channels = array_diff($input_channels, $current_channels);
            $deleted_channels = array_diff($current_channels, $input_channels);

            if (count($new_channels) > 0) {
                $user->channels()->attach($new_channels);
            }

            if (count($deleted_channels) > 0) {
                $user->channels()->detach($deleted_channels);
            }
        } else {
            // $user->channels()->detach();
        }

        return $this->model->find($id);
    }
}
