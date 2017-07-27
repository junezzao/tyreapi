<?php
class SaleNotes extends \Eloquent
{
    protected $table = "sales_notes";

    protected $fillable = ["admin_id", "sale_id", "notes"];

    public function getDates()
    {
        return [];
    }
}
