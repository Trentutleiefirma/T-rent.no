<?php 
namespace REDQ_RnB\Admin;

class Orders_List_Table_Ajax {
    public function __construct()
    {
        add_action( 'wp_ajax_rnb_order_table', [$this, 'rnb_order_table'] );
        add_action( 'wp_ajax_update_screen_options_columns', [$this, 'update_screen_options_columns'] );
        // Update options 
        add_action('init', [$this, 'update_screen_options']);
    }
    public function rnb_order_table(){

        $nonce = isset($_REQUEST['_nonce']) ? $_REQUEST['_nonce'] : '';
        if ( empty( $_REQUEST ) || ! wp_verify_nonce( $nonce, '_rnb_order_nonce' ) ) {
            $error = esc_html__('Nonce validation failed', 'redq-rental');
            wp_send_json_error($error); 
        }
        parse_str($_REQUEST['data'], $search_array);

        if (!isset($search_array['order_id'])) {
            $error = esc_html__('No order selected', 'redq-rental');
            wp_send_json_error( $error); 
        }
        if (!isset($search_array['action']) || $search_array['action'] == -1) {
            $error = esc_html__('No action selected', 'redq-rental');
            wp_send_json_error( $error); 
        }
        $return = array(
            'message' => $this->update_status($search_array['order_id'], $search_array['action']),
            'ID'      => $search_array['order_id']
        );
        wp_send_json_success( $return );
        wp_die();
    }
    public function update_screen_options_columns() {
        $nonce = isset($_REQUEST['_nonce']) ? $_REQUEST['_nonce'] : '';
        if ( empty( $_REQUEST ) || ! wp_verify_nonce( $nonce, '_rnb_order_nonce' ) ) {
            $error = esc_html__('Nonce validation failed', 'redq-rental');
            wp_send_json_error($error); 
        }
        if(is_array($_REQUEST['data'])){
            $data = $_REQUEST['data'];
        }else{
            $data = [];
        }
        update_option('rnb_screen_order_columns', $data);
        
        wp_die();
    }
   public function update_status($get_order, $action){
    if(empty($get_order)){
        return esc_html__('No order to Update', 'redq-rental');
    }
    $action_exclude_list = ['untrash', 'delete'];
    if(is_array(($get_order)) && !in_array($action, $action_exclude_list)){
        foreach($get_order as $id){
            $order = new \WC_Order($id);
             $order->update_status($action); // order note is optional, if you want to  add a note to order
        }
    }
    if($action == 'delete'){
        if(is_array(($get_order))){
            foreach($get_order as $id){
                $result =  wp_delete_post($id, true);
                if (is_wp_error($result)) {
                    echo esc_html__('Order delete failed.', 'redq-rental');
                } else {
                    echo esc_html__('Order successfully deleted.', 'redq-rental');
                }
            }
        }
    }
    if($action == 'untrash'){
        if(is_array(($get_order))){
            foreach($get_order as $id){
                $order = new \WC_Order($id);
                return $order->update_status('pending');
                if (is_wp_error($result)) {
                    echo esc_html__('Order restore failed.', 'redq-rental');
                } else {
                    echo esc_html__('Order successfully UnTrash.', 'redq-rental');
                }
            }
        }
    }
    
   } 
   /**
    * Update pagination option on submit on screen option 
    */
    public function update_screen_options(){
        if(isset($_REQUEST['rnb_admin_order_list_per_page'])){
            update_option('rnb_admin_order_list_per_page', absint($_REQUEST['rnb_admin_order_list_per_page']));
        }
    }
}