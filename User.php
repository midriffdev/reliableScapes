<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
include APPPATH . 'third_party/phpmailer/PHPMailerAutoload.php';
include APPPATH . 'third_party/phpmailer/class.smtp.php';
class User extends CI_Controller {
	
	public function __construct(){
        parent::__construct();
		$this->load->model(array('admin/User_model','api/Api_model','admin/client_model'));
		$this->load->library('email'); 
    }
	
	public function user_register()
	{
		$_POST = json_decode(file_get_contents("php://input"), true);
		$this->form_validation->set_rules('user_name', 'user name', 'required|valid_email|is_unique[rs_users.email]|xss_clean');
		$this->form_validation->set_rules('password', 'password', 'required|xss_clean');
		$this->form_validation->set_rules('industry_type', 'Industry', 'required|xss_clean');	
		if($this->form_validation->run() == FALSE)
		{
			
			if(form_error('user_name') == '<p>The user name field must contain a valid email address.</p>'){
				$error = 'Invalid Username';
			}else if(form_error('user_name') == '<p>The user name field must contain a unique value.</p>'){
				$error = 'Username already exists';
			}else{
				$error = 'All fields are required';
			}
			
			echo json_encode(array("status_code"=>0,"status_message"=>$error));
		}else{
			
			$user_data['email'] = $this->input->post('user_name');
			$user_data['password'] = password_hash($this->input->post('password'), PASSWORD_BCRYPT);
			$user_data['industry'] = $this->input->post('industry_type');
			$user_data['api_token']= random_string('alnum', 60);
			$response = $this->User_model->User_register($user_data);
			if(!empty($response)){
				echo json_encode(array("status_code"=>1,"status_message"=>"Account created successfully"));
			}else{
				echo json_encode(array("status_code"=>0,"status_message"=>"Internal server error"));
			}
			
		}
		
	}
	
	public function user_login()
	{
		$_POST = json_decode(file_get_contents("php://input"), true);
		$this->form_validation->set_rules("user_name", "User name", "trim|required|valid_email");
        $this->form_validation->set_rules("password", "Password", "trim|required");

        if ($this->form_validation->run() == FALSE)
        {
			
			if(form_error('user_name') == '<p>The user name field must contain a valid email address.</p>'){
				$error = "User doesn't exist";
			}else{
				$error = 'All fields are required';
			}
			echo json_encode(array("status_code"=>0,"status_message"=>$error));
			
        } else {
			$username = $this->input->post('user_name');
			$password = $this->input->post('password');
			$response = $this->User_model->get_login($username,$password);
			$login_data = '';
			if($response != 0){
				$login_data = array(
					"id"=>$response['id'],
					"token"=>$response['api_token'],
					"email"=>$response['email'],
					"first_name"=>$response['first_name'],
					"last_name"=>$response['last_name'],
					"industry"=>$response['industry'],
					"currency"=>$response['currency'],
					"locale"=>$response['locale']
				);
				
				$login_data['basic_information']['company_name'] = $response['company_name'];
				$login_data['basic_information']['phone_number'] = $response['phone_number'];
				$login_data['basic_information']['address'] = $response['address'];
				$login_data['basic_information']['optional_address'] = $response['optional_address'];
				$login_data['basic_information']['city'] = $response['city'];
				$login_data['basic_information']['state'] = $response['state'];
				$login_data['basic_information']['country'] = $response['country'];
				$login_data['basic_information']['zip_code'] = $response['zip_code'];
				$login_data['basic_information']['tax_number'] = $response['tax_number'];
				$login_data['basic_information']['abn_no'] = $response['abn_no'];
				$login_data['basic_information']['licence_no'] = $response['licence_no'];
				
				
				$login_data['additional_info']['company_email'] = $response['company_email'];
				$login_data['additional_info']['additional_number'] = $response['additional_number'];
				$login_data['additional_info']['fax_number'] = $response['fax_number'];
				$login_data['additional_info']['website'] = $response['website'];
				$login_data['additional_info']['industry'] = $response['industry'];
				
				/* getting user preferances */
				$user_prefrences = $this->Api_model->get_user_prefrences($response['id']);
				
				if(!empty($user_prefrences)){
					
					$login_data['preference'] = $user_prefrences;
				}
				
				$login_data['contract']  = base_url('assets/contract/qbcc_contract_format.pdf');
				
				echo json_encode(array("status_code"=>1,"status_message"=>"Login successfully","data"=>$login_data));
			}else{
				echo json_encode(array("status_code"=>0,"status_message"=>"Please check your username or password entered"));
			}
		}
	}
	
	public function forgot_password()
	{
		$_POST = json_decode(file_get_contents("php://input"), true);
		$this->form_validation->set_rules('user_name', 'user name', 'required|valid_email|is_unique[rs_users.email]|xss_clean');
		if ($this->form_validation->run() == FALSE)
        {
			if(form_error('user_name') == "<p>The user name field must contain a unique value.</p>"){
				
				$otp['api_password_otp'] = rand(pow(10, 4-1), pow(10, 4)-1);
				$email = $this->input->post('user_name');
				
				
				
				$config = array(
					'charset'=>'utf-8',
					'wordwrap'=> TRUE,
					'mailtype' => 'html'
				);

				$mail = new PHPMailer;
				//$mail->SMTPDebug = 2;
				$mail->isSMTP();
				$mail->SMTPOptions = array(
					'ssl' => array(
						'verify_peer' => false,
						'verify_peer_name' => false,
						'allow_self_signed' => true
					)
				);
				$mail->Host = 'mail.midriffinfosolution.com';
				$mail->SMTPAuth = true;
				$mail->Username = 'test@midriffinfosolution.com';
				$mail->Password = '1DXb06-)$7Ut';
				$mail->SMTPSecure = 'tls';
				$mail->Port = 587;
				$mail->ClearAllRecipients();
				$mail->setFrom('test@midriffinfosolution.com', 'ReliableScapes');
				$mail->addAddress($email);
				$mail->isHTML(true);
				$mail->Subject = 'Forgot Password.';
				$mail->Body    = 'Your one time forgot password pin '.$otp['api_password_otp'];
				$mail->AltBody = '';
			   
				$mail->send();
				
				$this->Api_model->update_otp($otp,$email);
				echo json_encode(array("status_code"=>1,"status_message"=>"Password sent to email"));
			}else{
				echo json_encode(array("status_code"=>0,"status_message"=>"Username doesn't exist"));
			}
		}else{
			echo json_encode(array("status_code"=>0,"status_message"=>"Username doesn't exist"));
		}
	}
	
	public function reset_password()
	{
		$_POST = json_decode(file_get_contents("php://input"), true);
		$this->form_validation->set_rules("user_name", "User name", "trim|required|valid_email");
        $this->form_validation->set_rules("password", "Password", "trim|required");
        $this->form_validation->set_rules("otp", "Password", "trim|required");
		if ($this->form_validation->run() == FALSE)
        {
			echo json_encode(array("status_code"=>0,"status_message"=>'All fields are required'));
		}else{
			
			$response = $this->Api_model->verify_otp($this->input->post('user_name'),$this->input->post('otp'));
			if($response != ''){
				
				$new_password['password'] = password_hash($this->input->post('password'), PASSWORD_BCRYPT);
				$this->Api_model->update_password($this->input->post('user_name'),$new_password);
				echo json_encode(array("status_code"=>1,"status_message"=>'Password successfully changed'));
			}else{
				echo json_encode(array("status_code"=>0,"status_message"=>'Code is not valid'));
			}
		}
	}
	
	public function my_account()
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
				$this->form_validation->set_rules("first_name", "first name", "trim|required");
				$this->form_validation->set_rules("last_name", "last name", "trim|required");

				if ($this->form_validation->run() == FALSE)
				{
					echo json_encode(array("status_code"=>0,"status_message"=>'All fields are required'));
				}else{
					
					$this->User_model->update_account_details($_POST,$response->id);
					echo json_encode(array("status_code"=>1,"status_message"=>'Profile updated successfully'));
				}
			}
		}
	}
	
	public function my_company()
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
				$company_data['company_name'] = $_POST['basic_information']['company_name'];
				$company_data['phone_number'] = $_POST['basic_information']['phone_number'];
				$company_data['address'] = $_POST['basic_information']['address'];
				$company_data['optional_address'] = $_POST['basic_information']['optional_address'];
				$company_data['city'] = $_POST['basic_information']['city'];
				$company_data['state'] = $_POST['basic_information']['state'];
				$company_data['country'] = $_POST['basic_information']['country'];
				$company_data['zip_code'] = $_POST['basic_information']['zip_code'];
				$company_data['tax_number'] = $_POST['basic_information']['tax_number'];
				$company_data['abn_no'] = $_POST['basic_information']['abn_no'];
				$company_data['licence_no'] = $_POST['basic_information']['licence_no'];
				
				$company_data['company_email'] = $_POST['additional_info']['company_email'];
				$company_data['additional_number'] = $_POST['additional_info']['additional_number'];
				$company_data['fax_number'] = $_POST['additional_info']['fax_number'];
				$company_data['website'] = $_POST['additional_info']['website'];
				$company_data['industry'] = $_POST['additional_info']['industry'];
				
				$this->User_model->update_account_details($company_data,$response->id);
				echo json_encode(array("status_code"=>1,"status_message"=>'Company updated successfully'));
			}
		}
	}
	
	function token_auth($token)
	{
		return $this->Api_model->check_token($token);
	}
	
}
?>