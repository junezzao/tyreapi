<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin - User Related Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    /*
     * Page Titles (on tab)
     */
    'page_title_fulfillment'            => 'Admin - Fulfillment',
    'page_title_create_order'           => 'Orders - Create Order',
    'page_title_view_order'             => '#:order_id - Order Details',
    'page_title_order_sheet'             => '#:order_id - Order Sheet',
    'page_title_return_slip'             => '#:order_id - Return Slip',

    /*
     * Page Titles (content-header)
     */
    'content_header_fulfillment'        => 'Fulfillment',
    'content_header_manual_order'       => 'Create Manual Order',
    'content_header_view_order'         => 'Order Details',
    'content_header_live_transactions'  => 'Live Transactions',

    /*
     * Sub-Titles (box-header)
     */
    'box_header_returns'        		=> 'Returns List',
    'box_header_returns_reject' 		=> 'Reject',
    'box_header_manual_order'   		=> 'Manual Order Form',
    'box_header_view_order'   			=> ':channel > Order #:order',
    'box_header_live_transactions'      => 'Orders',


    /*
     * Tabs
     */
    'returns_in_transit'        		=> 'In Transit',
    'returns_done'              		=> 'Done',

    /*
     * Returns List Table
     */
    'returns_list_table_hubwire_sku'    => 'Hubwire SKU',
    'returns_list_table_item_name'      => 'Item Name',
    'returns_list_table_order_id'       => 'Order ID',
    'returns_list_table_created_at'     => 'Created At',
    'returns_list_table_completed_at'   => 'Completed At',
    'returns_list_table_status'         => 'Status',
    'returns_list_table_actions'        => 'Actions',

    /*
     * Buttons
     */
    'button_restock'    				=> 'Restock',
    'button_reject'     				=> 'Reject',
    'button_cancel'                     => 'Cancel',
    'button_return'     				=> 'Return',
    'button_confirm'                    => 'Confirm',
    'button_yes'                        => 'Yes',
    'button_no'          			    => 'No',
    'button_submit'                     => 'Submit',
    'button_save'                       => 'Save',
    'button_done'                       => 'Done',
    'button_comment'                    => 'Comment',


    /*
     * Manual Order
     */
    'manual_order_form_label_merchant'  						=> 'Merchant',
    'manual_order_form_placeholder_merchant'					=> 'Select Merchant',
    'manual_order_form_label_channel'  							=> 'Channel',
    'manual_order_form_placeholder_channel' 					=> 'Select Channel',
    'manual_order_form_label_tp_code'  							=> 'Third Party Order',
    'manual_order_form_placeholder_tp_code' 					=> 'Third Party Order Number',
    'manual_order_form_label_order_date' 						=> 'Order Date',
    'manual_order_form_placeholder_order_date' 					=> 'Date of Order',
    'manual_order_form_placeholder_order_time' 					=> 'Time of Order',
    'manual_order_form_label_recipient_name' 					=> 'Name',
    'manual_order_form_placeholder_recipient_name' 				=> 'Recipient Name',
    'manual_order_form_label_recipient_contact' 				=> 'Contact Number',
    'manual_order_form_placeholder_recipient_contact' 			=> 'Recipient Contact Number',
    'manual_order_form_label_recipient_address' 				=> 'Address',
    'manual_order_form_placeholder_recipient_address_1' 		=> 'Recipient Address Street 1',
    'manual_order_form_placeholder_recipient_address_2' 		=> 'Recipient Address Street 2',
    'manual_order_form_placeholder_recipient_address_city' 		=> 'Recipient Address City',
    'manual_order_form_placeholder_recipient_address_state' 	=> 'Recipient Address State',
    'manual_order_form_placeholder_recipient_address_postcode' 	=> 'Recipient Address Postcode',
    'manual_order_form_placeholder_recipient_address_country' 	=> 'Recipient Address Country',


    'manual_order_form_label_customer_name' 					=> 'Name',
    'manual_order_form_placeholder_customer_name' 				=> 'Customer Name',
    'manual_order_form_label_customer_email' 					=> 'Email',
    'manual_order_form_placeholder_customer_email' 				=> 'Customer Email',
    'manual_order_form_label_customer_contact' 					=> 'Contact Number',
    'manual_order_form_placeholder_customer_contact' 			=> 'Customer Contact Number',
    'manual_order_form_label_customer_address' 					=> 'Address',
    'manual_order_form_placeholder_customer_address_1' 			=> 'Customer Address Street 1',
    'manual_order_form_placeholder_customer_address_2' 			=> 'Customer Address Street 2',
    'manual_order_form_placeholder_customer_address_city'		=> 'Customer Address City',
    'manual_order_form_placeholder_customer_address_state' 		=> 'Customer Address State',
    'manual_order_form_placeholder_customer_address_postcode' 	=> 'Customer Address Postcode',
    'manual_order_form_placeholder_customer_address_country' 	=> 'Customer Address Country',
    'manual_order_form_label_payment_type' 						=> 'Payment Type',
    'manual_order_form_placeholder_payment_type' 				=> 'Payment Type',
    'manual_order_form_label_cart_discount'                     => 'Cart Discount',
    'manual_order_form_placeholder_cart_discount'               => 'Cart Discount',
    'manual_order_form_help_cart_discount'                      => 'The cart discount amount given by third party. This amount should be distributed into the individual items below.',
    'manual_order_form_label_amount_paid'                       => 'Amount Paid',
    'manual_order_form_help_amount_paid'                        => 'Amount paid by customer inclusive of GST.',
    'manual_order_form_help_amount_paid_total'                  => 'Amount paid should equal to sum of sold price for all items (inclusive of tax) and shipping fees (inclusive of tax, if any), minus cart discount.',
    'manual_order_form_placeholder_currency'                    => 'Currency',
    'manual_order_form_placeholder_amount_paid'                 => 'Amount Paid',
    'manual_order_form_label_total_tax'                         => 'Total Tax',
    'manual_order_form_placeholder_total_tax'                   => 'Total Tax',
    'manual_order_form_label_shipping_provider'                 => 'Shipping Provider',
    'manual_order_form_placeholder_shipping_provider'           => 'Shipping Provider',
    'manual_order_form_label_shipping_fee'                      => 'Shipping Fee',
    'manual_order_form_placeholder_shipping_fee'                => 'Shipping Fee',
    'manual_order_form_label_shipping_no'                       => 'Shipping No.',
    'manual_order_form_placeholder_shipping_no'                 => 'Shipping No.',
    'manual_order_form_placeholder_find_sku'                    => 'Enter Hubwire SKU to add items',

    /*
     * View Order
     */
    'order_print_options_shipping_labels'       => 'Shipping Label(s)',
    'order_print_options_invoices'              => 'Invoice(s)',
    'order_print_options_tax_invoice'           => 'Tax Invoice',
    'order_print_options_credit_note'           => 'Credit Note',
    'order_print_options_order_sheet'           => 'Order Sheet',
    'order_print_options_return_slip'           => 'Return Slip',

    'order_label_paid_status' 					=> 'Paid Status',
    'order_label_shipping_provider' 			=> 'Shipping Provider',
    'order_placeholder_shipping_provider' 		=> 'Shipping Provider',
    'order_label_consignment_no' 				=> 'Consignment No.',
    'order_placeholder_consignment_no' 			=> 'Consignment No.',
    'order_label_notification_date'				=> 'Notification Date',
    'order_placeholder_find_item'               => 'Scan Hubwire SKU',
    'order_placeholder_store_credit' 			=> 'Store Credit',
    'order_placeholder_add_note'                => 'Add a new note...',
    'order_placeholder_add_comment'             => 'Add a new comment...',
    'order_comment_popup_label'                 => 'Add Comment',
    'order_placeholder_note_type'               => 'Select a note type',
    
    'order_tabs_label_notes'                    => 'Notes',
    'order_tabs_label_order_history'            => 'Order History',

    /* 
     * Live Transactions
     */
    'order_placeholder_tp_code'                 => 'Third Party Order Code/ID',
    'order_placeholder_date_range'              => 'Date Range',
    'order_placeholder_merchant'                => 'All Merchants',
    'order_placeholder_channel'                 => 'All Channels',
    'order_placeholder_status'                  => 'All Statuses',
    'order_placeholder_paid_status'             => 'All Paid Statuses',
    'order_placeholder_payment_type'            => 'All Payment Types',
    'order_placeholder_partially_fulfilled'     => 'Partially Fulfilled?',
    'order_placeholder_cancelled_status'        => 'Cancelled?',
    'order_placeholder_scan_order_id'           => 'Scan Order Number',

    'order_badge_btn_new'               =>'New',
    'order_badge_btn_new_orders'        =>'New Orders',
    'order_badge_btn_picking'           =>'Picking',
    'order_badge_btn_packing'           =>'Packing',
    'order_badge_btn_ready_to_ship'         =>'Ready To Ship',
    'order_badge_btn_partially_fulfilled'   =>'Partially Fulfilled',

    'order_table_merchant'          => 'Merchant Name',
    'order_table_channel'           => 'Channel Name',
    'order_table_order_no'          => 'Order No.',
    'order_table_customer_name'     => 'Customer Name',
    'order_table_created_at'        => 'Created At',
    'order_table_total'             => 'Total',
    'order_table_no_items'          => 'No. of Items',
    'order_table_status'            => 'Status',
    'order_table_paid_status'       => 'Paid Status',
    'order_table_payment_method'    => 'Payment Method',

    'order_button_filter'            => 'Filter',
    'order_button_clear_filter'      => 'Clear Filter',

    'order_label_scan_order_id'      => 'Search Order Number: ',

    /* Picking Manifest */
    'page_title_manifest_list'                 => 'Picking Manifest',
    'content_header_manifest_list'             => 'Picking Manifests',
    'box_title_manifest_list'                 => 'Picking Manifest List',
    'page_title_manifest'                   => 'Manifest #:manifest',
    'box_title_manifest'                 => 'Picking Manifest #:manifest',  

    'manifest_table_id'                 => 'ID',
    'manifest_table_status'             => 'Status',
    'manifest_table_attended_by'        => 'Attended By',
    'manifest_table_picked_up'          => 'Picked Up At',
    'manifest_table_created_at'         => 'Created At',
    'manifest_table_created_by'         => 'Created By',
    'manifest_table_tp_order_date'      => 'Third Party Order Date',
    'manifest_table_updated_at'         => 'Updated At',
    'manifest_table_completed_at'       => 'Completed At',
    'manifest_table_actions'            => 'Actions',
    'manifest_table_channel_name'       => 'Channel Name',
    'manifest_table_cancelled_status'   => 'Cancelled',

    'manifest_table_hubwire_sku'          => 'Hubwire SKU',
    'manifest_table_item_name'            => 'Item Name',
    'manifest_table_coordinates'            => 'Coordinates',
    'manifest_table_order_no'            => 'Order No.',
    'manifest_table_item_id'            => 'Order Item No.',

    'manifest_btn_oos'                  => 'Out of Stock',
    'manifest_btn_generate'             => 'Generate New Picking Manifest',
    'manifest_btn_pick_up'              =>  'Pick Up',
    'manifest_btn_view'                 =>  'View',
    'manifest_btn_completed'            =>  'Completed',
    'manifest_btn_export_post_laju'     =>  'Export Post Laju EST',
    'manifest_btn_print_docs'           =>  'Print Documents',

    'manifest_placeholder_barcode'      => 'Enter Barcode/Hubwire SKU',
    'manifest_label_item'               => 'Item',

    'tab_title_picking_items'           => 'Picking Items',
    'tab_title_orders'                  => 'Orders',    

];
