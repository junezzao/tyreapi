<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class OrderNote extends BaseModel
{
    protected $table = "order_notes";

    protected $guarded = ['id'];

    protected $fillable = ["order_id", "user_id", "notes", "note_type", "note_status", "previous_note_id"];

}
