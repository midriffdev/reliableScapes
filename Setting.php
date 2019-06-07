<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class Setting extends CI_Controller {
	
	public function __construct(){
        parent::__construct();
		$this->load->model(array('admin/User_model','api/api_model','admin/client_model'));
		$this->load->library('email'); 
    }
	
	public function prefrences()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$_POST = json_decode(file_get_contents("php://input"), true);
				
				$preference_data['user_id']					= $response->id;
				$preference_data['email_estimate_message'] 	= $_POST['email_estimate_message'];
				$preference_data['email_invoice_message'] 	= $_POST['email_invoice_message'];
				$preference_data['client_opens_email'] 		= $_POST['client_opens_email'];
				$preference_data['email_not_delivered'] 	= $_POST['email_not_delivered'];
				$preference_data['client_signs_document'] 	= $_POST['clientsigns_document'];

				$this->api_model->save_preferences($response->id,$preference_data);
				echo json_encode(array("status_code"=>1,"status_message"=>'Preferences added successfully'));
			}
		}
	}
	
	function token_auth($token)
	{
		return $this->api_model->check_token($token);
	}
}
?>