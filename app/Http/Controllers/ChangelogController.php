<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Response;
use Bican\Roles\Models\Role;

use Illuminate\Support\Collection;
use App\Http\Requests;
use App\Models\Admin\Changelog;
use App\Models\User;
use App\Helpers\Helper;

use Session;
use Config;
use Form;
use Activity;


class ChangelogController extends Controller
{
    //
    protected $adminId;

    public function __construct()
    {

        $this->middleware('auth:web', ['except' => ['viewChangelogPublic', 'getChangelog']]);
        $this->adminId = Session::get('user')['user_id'];
    }

	public function index()
	{
        $data['changelogs'] = Changelog::all();
        $data['admin'] = User::find($this->adminId);
        $data['changelogType'] = config('globals.changelog_type_dropdown');

        foreach ($data['changelogs'] as $changelog) {
            $actions = '';
            if ($data['admin']->is('superadministrator')) {
                $actions = Form::open(array('role'=>'form', 'class'=>'form-inline', 'method' => 'GET')) . '<a href="'.route('1.0.hw.changelog.edit', $changelog->id).'" class="btn btn-link no-padding">Edit</a>'.Form::close()." | ";

                $actions .= Form::open(array('url' => route('1.0.hw.changelog.destroy',$changelog->id), 'role'=>'form', 'class'=>'form-inline', 'method' => 'DELETE')) . '<button type="submit" class="btn btn-link no-padding confirmation">Delete</button>'.Form::close();
            }

            $changelog->type = isset($data['changelogType'][$changelog->type])? $data['changelogType'][$changelog->type] : '';
            $changelog->actions = $actions;
            if (isset($data['admin']->timezone) && !empty($data['admin']->timezone))
                $changelog->created_at = Helper::convertTimeToUserTimezone($changelog->created_at, $data['admin']->timezone);
        }

        return view('changelog.index', $data);
    }

    public function create() 
    {
        $data['changelogType'] = config('globals.changelog_type_dropdown');
        return view('changelog.create', $data);
    }

    public function store(Request $request)
    {
        if (trim(strip_tags($request->input('content'))) == '') {
            return back()->withInput()->withErrors(["content" => "The content field is required."]);
        }

        $this->validate($request, array(
            'title'         => 'required',
            'type'          => 'required|integer',
            'content'       => 'required',
        ));

    	$changelog = Changelog::create($request->all());

        Activity::log('Changelog ('.$changelog->id.') was created', $this->adminId);
        flash()->success('The changelog was successfully created.');

        return redirect()->route('1.0.hw.changelog.index');
    }

    public function edit($id)
    {
        $data['changelogType'] = config('globals.changelog_type_dropdown');
        $data['admin'] = User::find($this->adminId);
        $data['changelog'] = Changelog::findOrFail($id);
        return view('changelog.edit', $data);
    }

    public function update(Request $request, $id)
    {
        if (trim(strip_tags($request->input('content'))) == '') {
            return back()->withInput()->withErrors(["content" => "The content field is required."]);
        }

        $this->validate($request, array(
            'title'         => 'required',
            'type'          => 'required|integer',
            'content'       => 'required',
        ));

    	$changelog = Changelog::findOrFail($id);
    	$changelog->update($request->all());

    	Activity::log('Changelog ('.$id.') was updated', $this->adminId);
    	flash()->success('The changelog was successfully updated.');

        return redirect()->route('1.0.hw.changelog.index');
    }

    public function destroy($id)
    {
    	$changelog = Changelog::findOrFail($id);
        $ack  = $changelog->delete($id);

        Activity::log('Changelog ('.$id.') was deleted', $this->adminId);
        flash()->success('The changelog was successfully deleted.');
        return redirect()->route('1.0.hw.changelog.index');
    }

    public function viewChangelogPublic(Request $request, $type)
    {   
        $data['type'] = $type;
        return view('changelog.vendor_view', $data);
    }

    public function getChangelog(Request $request)
    {   
        $changelog = [];
        if (!is_null(config('globals.changelog_type.'.$request->input('type')))) 
            $changelog = Changelog::where('type', (int)config('globals.changelog_type.'.$request->input('type')))->orderBy('created_at', 'desc')->get();

        return response()->json(["success"=>true, "changelog"=>$changelog]);
    }
}
