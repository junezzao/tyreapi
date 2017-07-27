<?php
class Menu extends \Eloquent
{
    protected $connection = 'front';
    protected $primaryKey = 'menu_id';
    
    protected $table = 'menus';
    protected $fillable = array('menu_content','channel_id','updated_at','created_at');
}
