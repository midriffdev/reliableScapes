<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class Payment extends CI_Controller {
	
	public function __construct(){
        parent::__construct();
		$this->load->model(array('admin/User_model','api/api_model','admin/client_model'));
		$this->load->library('email'); 
    }
	
	public function index()
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
				$payment_data = array();
				foreach($_POST as $key=>$val)
				{
					$payment_data[] = array(
						"user_id"=>$response->id,
						"estimate_id"=>$val['estimate_id'],
						"installment_name"=>$val['installment_name'],
						"installment_type"=>$val['installment_type'],
						"installment_value"=>$val['installment_value'],
					);
				}
				$this->api_model->insert_payments($payment_data);
				echo json_encode(array("status_code"=>1,"status_message"=>"Payments created successfully"));
			}
		}
	}
	
	function token_auth($token)
	{
		return $this->api_model->check_token($token);
	}
}
?>