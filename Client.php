<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
include APPPATH . 'third_party/phpmailer/PHPMailerAutoload.php';
include APPPATH . 'third_party/phpmailer/class.smtp.php';
class Client extends CI_Controller {
	
	public function __construct(){
        parent::__construct();
		$this->load->model(array('admin/user_model','api/api_model','admin/client_model'));
		$this->load->library('email'); 
    }
	
	public function create_client()
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
				
				/* Prepare data */
				$client_data['user_id'] 		= $response->id;
				$client_data['client_name'] 	= $_POST['basic_information']['name'];
				$client_data['client_email'] 	= $_POST['basic_information']['email_address'];
				$client_data['phone_mobile'] 	= $_POST['basic_information']['mobile'];
				$client_data['phone_other'] 	= $_POST['basic_information']['phone'];
				$client_data['address'] 		= $_POST['billing_address']['address1'];
				$client_data['additional_address'] = $_POST['billing_address']['address2'];
				$client_data['city'] 			= $_POST['billing_address']['city'];
				$client_data['state'] 			= $_POST['billing_address']['state'];
				$client_data['zipcode'] 		= $_POST['billing_address']['zip_code'];
				$client_data['service_address_1']= $_POST['service_address']['service_address1'];
				$client_data['service_address_2']= $_POST['service_address']['service_address2'];
				$client_data['service_city'] 	= $_POST['service_address']['service_city'];
				$client_data['service_state'] 	= $_POST['service_address']['service_state'];
				$client_data['service_zipcode'] = $_POST['service_address']['service_zip_code'];
				$client_data['private_notes'] 	= $_POST['private_notes'];
				
				if(!empty($_POST['client_id'])){
					$client_response = $this->api_model->get_client_detail($_POST['client_id']);
					if(!empty($client_response)){
						/* Update client data */
						$this->api_model->update_client($_POST['client_id'],$client_data);
						echo json_encode(array("status_code"=>1,"status_message"=>"Client updated successfully"));
					}else{
						$email_response = $this->api_model->get_email_exist($_POST['basic_information']['email_address']);
						if(!empty($email_response)){
							echo json_encode(array("status_code"=>0,"status_message"=>"account already exist"));
						}else{
							$client_response = array();
							$client_id = $this->api_model->create_client($client_data);
							
							$client_response['client_id'] 	= $client_id;
							$client_response['basic_information']['name'] 			= $_POST['basic_information']['name'];
							$client_response['basic_information']['email_address']	= $_POST['basic_information']['email_address'];
							$client_response['basic_information']['mobile'] 		= $_POST['basic_information']['mobile'];
							$client_response['basic_information']['phone'] 			= $_POST['basic_information']['phone'];
							$client_response['billing_address']['address1'] 		= $_POST['billing_address']['address1'];
							$client_response['billing_address']['address2'] 		= $_POST['billing_address']['address2'];
							$client_response['billing_address']['city'] 			= $_POST['billing_address']['city'];
							$client_response['billing_address']['state'] 			= $_POST['billing_address']['state'];
							$client_response['billing_address']['zip_code'] 		= $_POST['billing_address']['zip_code'];
							
							$client_response['service_address']['service_address1'] 	= $_POST['service_address']['service_address1'];
							$client_response['service_address']['service_address2'] 	= $_POST['service_address']['service_address2'];
							$client_response['service_address']['service_city'] 		= $_POST['service_address']['service_city'];
							$client_response['service_address']['service_state'] 		= $_POST['service_address']['service_state'];
							$client_response['service_address']['service_zip_code'] 	= $_POST['service_address']['service_zip_code'];
							$client_response['private_notes'] 	= $_POST['private_notes'];
							
							echo json_encode(array("status_code"=>1,"status_message"=>"Client created successfully","data"=>$client_response));
						}
					}
				}else{
					/* check amil exist */
					$email_response = $this->api_model->get_email_exist($_POST['basic_information']['email_address']);
					if(!empty($email_response)){
						echo json_encode(array("status_code"=>0,"status_message"=>"account already exist"));
					}else{
						$client_response = array();
						$client_id = $this->api_model->create_client($client_data);
						
						$client_response['client_id'] 	= $client_id;
						$client_response['basic_information']['name'] 			= $_POST['basic_information']['name'];
						$client_response['basic_information']['email_address']	= $_POST['basic_information']['email_address'];
						$client_response['basic_information']['mobile'] 		= $_POST['basic_information']['mobile'];
						$client_response['basic_information']['phone'] 			= $_POST['basic_information']['phone'];
						$client_response['billing_address']['address1'] 		= $_POST['billing_address']['address1'];
						$client_response['billing_address']['address2'] 		= $_POST['billing_address']['address2'];
						$client_response['billing_address']['city'] 			= $_POST['billing_address']['city'];
						$client_response['billing_address']['state'] 			= $_POST['billing_address']['state'];
						$client_response['billing_address']['zip_code'] 		= $_POST['billing_address']['zip_code'];
						
						$client_response['service_address']['service_address1'] 	= $_POST['service_address']['service_address1'];
						$client_response['service_address']['service_address2'] 	= $_POST['service_address']['service_address2'];
						$client_response['service_address']['service_city'] 		= $_POST['service_address']['service_city'];
						$client_response['service_address']['service_state'] 		= $_POST['service_address']['service_state'];
						$client_response['service_address']['service_zip_code'] 	= $_POST['service_address']['service_zip_code'];
						$client_response['private_notes'] 	= $_POST['private_notes'];
						
						echo json_encode(array("status_code"=>1,"status_message"=>"Client created successfully","data"=>$client_response));
					}
				}
			}
		}
	}
	
	public function get_clients()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$get_clients = $this->client_model->all_clients($response->id);
				$client_list = array();
				if(!empty($get_clients)){
					
					foreach($get_clients as $key=>$val){
						$client_list[$key]['client_id'] 	= $val['client_id'];
						$client_list[$key]['basic_information']['name'] 		= $val['client_name'];
						$client_list[$key]['basic_information']['email_address']= $val['client_email'];
						$client_list[$key]['basic_information']['mobile'] 		= $val['phone_mobile'];
						$client_list[$key]['basic_information']['phone'] 		= $val['phone_other'];
						$client_list[$key]['billing_address']['address1'] 		= $val['address'];
						$client_list[$key]['billing_address']['address2'] 		= $val['additional_address'];
						$client_list[$key]['billing_address']['city'] 			= $val['city'];
						$client_list[$key]['billing_address']['state'] 			= $val['state'];
						$client_list[$key]['billing_address']['zip_code'] 		= $val['zipcode'];
						
						$client_list[$key]['service_address']['service_address1'] 	= $val['service_address_1'];
						$client_list[$key]['service_address']['service_address2'] 	= $val['service_address_2'];
						$client_list[$key]['service_address']['service_city'] 		= $val['service_city'];
						$client_list[$key]['service_address']['service_state'] 		= $val['service_state'];
						$client_list[$key]['service_address']['service_zip_code'] 	= $val['service_zipcode'];
						$client_list[$key]['private_notes'] 	= $val['private_notes'];
					}
				}
				echo json_encode(array("status_code"=>1,"status_message"=>"Client list","data"=>$client_list));
			}
		}
	}
	
	public function delete_client()
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
				$this->client_model->delete_client($_POST['client_id']);
				echo json_encode(array("status_code"=>1,"status_message"=>"Client deleted successfully"));
			}
		}
	}
	
	public function search_clients()
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
				$get_clients = $this->client_model->all_search_clients($_POST['keyword']);
				
				$client_list = array();
				if(!empty($get_clients)){
					
					foreach($get_clients as $key=>$val){
						$client_list[$key]['client_id'] 	= $val['client_id'];
						$client_list[$key]['basic_information']['name'] 		= $val['client_name'];
						$client_list[$key]['basic_information']['email_address']= $val['client_email'];
						$client_list[$key]['basic_information']['mobile'] 		= $val['phone_mobile'];
						$client_list[$key]['basic_information']['phone'] 		= $val['phone_other'];
						$client_list[$key]['billing_address']['address1'] 		= $val['address'];
						$client_list[$key]['billing_address']['address2'] 		= $val['additional_address'];
						$client_list[$key]['billing_address']['city'] 			= $val['city'];
						$client_list[$key]['billing_address']['state'] 			= $val['state'];
						$client_list[$key]['billing_address']['zip_code'] 		= $val['zipcode'];
						
						$client_list[$key]['service_address']['service_address1'] 	= $val['service_address_1'];
						$client_list[$key]['service_address']['service_address2'] 	= $val['service_address_2'];
						$client_list[$key]['service_address']['service_city'] 		= $val['service_city'];
						$client_list[$key]['service_address']['service_state'] 		= $val['service_state'];
						$client_list[$key]['service_address']['service_zip_code'] 	= $val['service_zipcode'];
						$client_list[$key]['private_notes'] 	= $val['private_notes'];
					}
				}
				echo json_encode(array("status_code"=>1,"status_message"=>"Client list","data"=>$client_list));
			}
		}
	}
	
	public function add_tax()
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
				$tax_data['user_id']	= $response->id;
				$tax_data['tax_name']	= $_POST['tax_name'];
				$tax_data['tax_amount']	= $_POST['rate'];
				
				$this->api_model->add_tax($tax_data);
				echo json_encode(array("status_code"=>1,"status_message"=>"Tax added successfully"));
			}
		}
	}
	
	public function get_tax()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$get_tex = $this->client_model->all_taxes();
				$tax_list = array();
				if(!empty($get_tex)){
					
					foreach($get_tex as $key=>$val){
						$tax_list[$key]['tax_id'] = $val['tax_id'];
						$tax_list[$key]['tax_name'] = $val['tax_name'];
						$tax_list[$key]['tax_amount'] = $val['tax_amount'];
					}
				}
				echo json_encode(array("status_code"=>1,"status_message"=>"Tax list","data"=>$tax_list));
			}
		}
	}
	
	public function delete_tax()
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
				$this->user_model->delete_tax($_POST['tax_id']);
				echo json_encode(array("status_code"=>1,"status_message"=>"Tax deleted successfully"));
			}
		}
	}
	
	public function add_item()
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
				
				$item_data['user_id'] = $response->id;
				$item_data['item_name'] = $_POST['item_name'];
				$item_data['item_rate'] = $_POST['rate'];
				$item_data['tax_id'] = implode(',',$_POST['taxes']);
				$item_data['notes'] = $_POST['description'];
				
				if(!empty($_POST['item_id'])){
					/* update item */
					$this->client_model->update_item($_POST['item_id'],$item_data);
					echo json_encode(array("status_code"=>1,"status_message"=>"Item updated successfully"));
				}else{
					$this->client_model->add_item($item_data);
					echo json_encode(array("status_code"=>1,"status_message"=>"Item created successfully"));
				}
			}
		}
	}
	
	public function get_items()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$item_list = $this->client_model->all_items($response->id);
				
				$items = array();
				if(!empty($item_list)){
					foreach($item_list as $key=>$val){
						
						$items[$key]['item_id'] 	= $val['item_id'];
						$items[$key]['item_name'] 	= $val['item_name'];
						$items[$key]['rate'] 		= $val['item_rate'];
						$items[$key]['description'] = $val['notes'];
						
						/* Items's tax info */
						$item_tax_list = $this->client_model->all_taxes();
						$tax_list = array();
						$selected_tax_ids = explode(',',$val['tax_id']);
						if(!empty($item_tax_list)){
							foreach($item_tax_list as $k=>$tax){
								$tax_list[$k]['tax_id'] 	= $tax['tax_id'];
								$tax_list[$k]['tax_name'] 	= $tax['tax_name'];
								$tax_list[$k]['tax_amount'] = $tax['tax_amount'];
								if(in_array($tax['tax_id'],$selected_tax_ids)){
									$tax_list[$k]['enabled'] = true;
								}else{
									$tax_list[$k]['enabled'] = false;
								}
							}
						}
						$items[$key]['taxes'] 		= $tax_list;
					}
				}
				echo json_encode(array("status_code"=>1,"status_message"=>"Item List","data"=>$items));
			}
		}
	}
	
	public function delete_item()
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
				$this->client_model->delete_item($_POST['item_id']);
				echo json_encode(array("status_code"=>1,"status_message"=>"Item deleted successfully"));
			}
		}
	}
	
	public function search_item()
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
				$item_list = $this->client_model->all_search_items($_POST['keyword']);
				$items = array();
				if(!empty($item_list)){
					foreach($item_list as $key=>$val){
						
						$items[$key]['item_id'] 	= $val['item_id'];
						$items[$key]['item_name'] 	= $val['item_name'];
						$items[$key]['rate'] 		= $val['item_rate'];
						$items[$key]['description'] = $val['notes'];
						
						/* Items's tax info */
						$item_tax_list = $this->client_model->all_taxes();
						$tax_list = array();
						$selected_tax_ids = explode(',',$val['tax_id']);
						if(!empty($item_tax_list)){
							foreach($item_tax_list as $k=>$tax){
								$tax_list[$k]['tax_id'] 	= $tax['tax_id'];
								$tax_list[$k]['tax_name'] 	= $tax['tax_name'];
								$tax_list[$k]['tax_amount'] = $tax['tax_amount'];
								if(in_array($tax['tax_id'],$selected_tax_ids)){
									$tax_list[$k]['enabled'] = true;
								}else{
									$tax_list[$k]['enabled'] = false;
								}
							}
						}
						$items[$key]['taxes'] 		= $tax_list;
					}
				}
				echo json_encode(array("status_code"=>1,"status_message"=>"Item List","data"=>$items));
			}
		}
	}
	
	function token_auth($token)
	{
		return $this->api_model->check_token($token);
	}
	
}
?>